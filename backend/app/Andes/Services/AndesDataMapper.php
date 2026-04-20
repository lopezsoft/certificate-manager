<?php

namespace App\Andes\Services;

use App\Andes\DTOs\CertificateEmissionRequest;
use App\Andes\DTOs\IdentityValidationRequest;
use App\Andes\Enums\AndesVigenciaEnum;
use App\Models\CertificateRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * AndesDataMapper
 *
 * Responsabilidad única: traducir entre el modelo de datos interno del
 * Certificate Manager y los códigos/estructuras que espera ANDES (ID y PKI).
 *
 * Principios:
 * - Lee andes_code / andes_cert_type desde BD vía Cache (no hardcoded)
 * - Single Responsibility: solo mapea, no llama APIs externas
 * - Inmutable: no persiste nada, solo transforma DTOs
 */
class AndesDataMapper
{
    private const CACHE_TTL_SECONDS = 86400; // 24h — datos de referencia casi estáticos

    /**
     * Devuelve el TipoDoc ANDES para un identity_document_id interno.
     *
     * @throws \RuntimeException si el documento no tiene andes_code configurado
     */
    public function mapIdentityDocumentToAndes(int $identityDocumentId): int
    {
        $cacheKey = "andes_identity_doc_map_{$identityDocumentId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($identityDocumentId) {
            $record = \DB::table('identity_documents')
                ->where('id', $identityDocumentId)
                ->select('id', 'code', 'document_name', 'andes_code')
                ->first();

            if (! $record) {
                throw new \RuntimeException(
                    "Tipo de documento con id={$identityDocumentId} no existe en identity_documents."
                );
            }

            if ($record->andes_code === null) {
                throw new \RuntimeException(
                    "El tipo de documento '{$record->document_name}' (id={$identityDocumentId}) "
                    . "no tiene andes_code configurado. Ejecute UpdateIdentityDocumentsAndesCodeSeeder."
                );
            }

            return (int) $record->andes_code;
        });
    }

    /**
     * Devuelve el tipoCert ANDES para un type_organization_id interno.
     *
     * @throws \RuntimeException si la organización no tiene andes_cert_type configurado
     */
    public function mapOrganizationTypeToAndesCertType(int $typeOrganizationId): int
    {
        $cacheKey = "andes_org_type_map_{$typeOrganizationId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($typeOrganizationId) {
            $record = \DB::table('type_organization')
                ->where('id', $typeOrganizationId)
                ->select('id', 'description', 'andes_cert_type')
                ->first();

            if (! $record) {
                throw new \RuntimeException(
                    "Tipo de organización con id={$typeOrganizationId} no existe en type_organization."
                );
            }

            if ($record->andes_cert_type === null) {
                throw new \RuntimeException(
                    "El tipo de organización '{$record->description}' (id={$typeOrganizationId}) "
                    . "no tiene andes_cert_type configurado. Ejecute UpdateTypeOrganizationAndesCertTypeSeeder."
                );
            }

            return (int) $record->andes_cert_type;
        });
    }

    /**
     * Devuelve el código DANE de la ciudad para el campo Municipio/municipioEnt de ANDES.
     * cities.city_code ya es código DANE → mapeo directo.
     *
     * @throws \RuntimeException si la ciudad no existe
     */
    public function getCityDaneCode(int $cityId): int
    {
        $cacheKey = "city_dane_code_{$cityId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($cityId) {
            $record = \DB::table('cities')
                ->where('id', $cityId)
                ->select('id', 'city_code')
                ->first();

            if (! $record) {
                throw new \RuntimeException("Ciudad con id={$cityId} no existe en cities.");
            }

            return (int) $record->city_code;
        });
    }

    /**
     * Divide un nombre completo en nombres y apellidos.
     *
     * Heurística: asume que el nombre tiene al menos 4 palabras.
     * Con 4+ palabras: las últimas 2 son apellidos, las primeras son nombres.
     * Con 3 palabras: última 1 es apellido, las 2 primeras son nombres.
     * Con 1-2 palabras: todo va a nombres, apellidos queda vacío.
     *
     * Ejemplo: "LEWIS OSWALDO LOPEZ GOMEZ" → ['LEWIS OSWALDO', 'LOPEZ GOMEZ']
     */
    public function splitFullName(string $fullName): array
    {
        $parts = array_filter(explode(' ', trim($fullName)));
        $parts = array_values($parts);
        $count = count($parts);

        if ($count >= 4) {
            $apellidos = implode(' ', array_slice($parts, -2));
            $nombres   = implode(' ', array_slice($parts, 0, $count - 2));
        } elseif ($count === 3) {
            $apellidos = $parts[2];
            $nombres   = implode(' ', array_slice($parts, 0, 2));
        } else {
            $nombres   = implode(' ', $parts);
            $apellidos = '';
        }

        return [
            'nombres'   => $nombres,
            'apellidos' => $apellidos,
        ];
    }

    /**
     * Construye el DTO CertificateEmissionRequest a partir de un CertificateRequest Eloquent.
     * El modelo DEBE tener cargadas las relaciones: identity, organization, city, company.company.city
     *
     * @param CertificateRequest $cert   Solicitud con relaciones cargadas
     * @param int    $formato            AndesFormatEnum value (2=físico, 3=PKCS10, 4=virtual)
     * @param int    $vigenciaYears      1 o 2 años
     * @param string $soporteBase64      ZIP con documentos de soporte en base64
     * @param string|null $pin           PIN opcional (mín 10 chars alfanuméricos)
     */
    public function buildCertificateEmissionRequest(
        CertificateRequest $cert,
        int    $formato,
        int    $vigenciaYears,
        string $soporteBase64,
        ?string $pin = null,
    ): CertificateEmissionRequest {
        $tipoCert   = $this->mapOrganizationTypeToAndesCertType($cert->type_organization_id);
        $tipoDoc    = $this->mapIdentityDocumentToAndes($cert->identity_document_id);
        $municipio  = $this->getCityDaneCode($cert->city_id);
        $vigencia   = AndesVigenciaEnum::fromYears($vigenciaYears)->value;

        $name = $this->splitFullName($cert->legal_representative);

        // fechaCert: ANDES espera la fecha de vencimiento del certificado
        $fechaCert = Carbon::now()
            ->addYears($vigenciaYears)
            ->format('Y-m-d');

        $params = [
            'tipoCert'      => $tipoCert,
            'tipoDoc'       => $tipoDoc,
            'documento'     => preg_replace('/[^0-9]/', '', $cert->document_number),
            'nombres'       => $name['nombres'],
            'apellidos'     => $name['apellidos'],
            'municipio'     => $municipio,
            'direccion'     => $cert->address,
            'email'         => $cert->company?->email ?? '',
            'emailEnt'      => $cert->company?->email ?? '',
            'celular'       => $cert->mobile,
            'fechaCert'     => $fechaCert,
            'formato'       => $formato,
            'vigenciaCert'  => $vigencia,
            'soporte'       => $soporteBase64,
            'telefono'      => $cert->phone,
            'pin'           => $pin,
        ];

        // Datos adicionales para Persona Jurídica
        if ($tipoCert === 10 && $cert->company) {
            $company       = $cert->company;
            $municipioEnt  = $this->getCityDaneCode($company->city_id);
            $tipoDocEnt    = $this->mapIdentityDocumentToAndes($company->identity_document_id);

            $params['tipoDocEnt']   = $tipoDocEnt;
            $params['documentoEnt'] = preg_replace('/[^0-9]/', '', $company->dni);
            $params['razonSocial']  = $company->company_name;
            $params['municipioEnt'] = $municipioEnt;
            $params['direccionEnt'] = $company->address;
            $params['cargo']        = $cert->info ?? 'Representante Legal';
        }

        return new CertificateEmissionRequest(...$params);
    }

    /**
     * Construye el DTO IdentityValidationRequest para iniciar validación ANDES ID.
     */
    public function buildIdentityValidationRequest(CertificateRequest $cert): IdentityValidationRequest
    {
        $andesDocType = $this->mapIdentityDocumentToAndes($cert->identity_document_id);

        $name = $this->splitFullName($cert->legal_representative);

        return new IdentityValidationRequest(
            idExpeditionDate: '', // ANDES ID lo requiere pero no lo almacenamos — vendrá del usuario en request
            idNumber:         preg_replace('/[^0-9]/', '', $cert->document_number),
            idType:           (string) $andesDocType,
            recentPhoneNumber: $cert->mobile,
            lastName:         $name['apellidos'],
        );
    }

    /**
     * Invalida el caché de mapeo para forzar recarga desde BD.
     * Útil cuando se ejecuta un seeder de homologación.
     */
    public function clearMappingCache(): void
    {
        // La invalidación granular requeriría iterar todos los IDs;
        // en cambio se usa un tag o se deja expirar (TTL 24h).
        // Para entornos con cache que soporte tags:
        // Cache::tags(['andes_mapping'])->flush();
    }
}

