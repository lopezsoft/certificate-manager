<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Modules\Viafirma\Application\Commands\IssueCertificateCommand;
use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Application\DTOs\ProfileDescriptor;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrInputDto;
use App\Modules\Viafirma\Application\Services\DnPatternValidator;
use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Domain\Mappers\IdentityTypeMapper;
use App\Modules\Viafirma\Domain\Mappers\ProfileTypeMapper;
use App\Modules\Viafirma\Infrastructure\Crypto\CsrBuilderFactory;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * IssueCertificateUseCase — orquesta el ciclo completo de emisión (V-204):
 *
 *   1) Resolver perfil (FE_PJ/FE_PN) y datos del CSR desde catálogos productivos.
 *   2) Obtener `dnPattern`, `codProfile` y `validity` del perfil vía Viafirma.
 *   3) Generar par RSA-2048 LOCALMENTE.
 *   4) Construir el CSR con el builder correspondiente (Strategy).
 *   5) Validar CSR contra `dnPattern` del perfil (V-211).
 *   6) Guardar la llave privada cifrada en el KeyVault.
 *   7) `POST /request/fromCSR` → obtener codRequest + publicId (ambos directos, API v3.4.53).
 *   8) Persistir el agregado `viafirma_certificate_requests` (estado SUBMITTED).
 *   9) Auditoría: `change_histories` + `viafirma_status_history`.
 *  10) Despachar el primer poll job (Sprint 3).
 *
 *  Toda la operación va en transacción de BD; la generación de llave + submit
 *  remoto se hacen ANTES del commit (rollback automático si Viafirma falla).
 */
final class IssueCertificateUseCase
{
    public function __construct(
        private readonly CryptoServiceContract $crypto,
        private readonly CsrBuilderFactory $csrBuilderFactory,
        private readonly KeyVault $keyVault,
        private readonly ViafirmaClient $client,
        private readonly ViafirmaCertificateRequestRepositoryContract $repository,
        private readonly IdentityTypeMapper $identityTypeMapper,
        private readonly ProfileTypeMapper $profileTypeMapper,
        private readonly DnPatternValidator $dnValidator,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(IssueCertificateCommand $cmd): ViafirmaCertificateRequest
    {
        $cr = CertificateRequest::query()
            ->with(['company.country', 'company.city.department', 'identity', 'organization', 'company.identity_document'])
            ->findOrFail($cmd->certificateRequestId);

        $existing = $this->repository->findByCertificateRequestId($cr->id);
        if ($existing !== null && !$existing->isFailed()) {
            throw new ViafirmaException(
                "La solicitud {$cr->id} ya tiene un trámite Viafirma en curso (id={$existing->id}, state={$existing->internal_state?->value})."
            );
        }

        // ── 1) Resolver perfil + datos -------------------------------------
        $profile      = $this->resolveProfile($cr);
        $identityType = $cmd->identityTypeOverride ?? $this->resolveIdentityType($cr, $profile);
        $this->enforceOrganizationTypeRule($profile, $cmd->organizationType);

        $countryCode = strtoupper((string) ($cr->company?->country?->abbreviation_A2 ?? 'CO'));

        // ── 2) Obtener perfil Viafirma -------------------------------------
        $raCode = (string) config('viafirma.ra_code');
        if ($raCode === '') {
            throw new ViafirmaException('config(viafirma.ra_code) está vacío — verifique VIAFIRMA_RA_CODE.');
        }

        $remoteProfile = $this->pickProfile($this->client->getProfiles($raCode), $profile);

        // ── 3-4) Generar llave + CSR ---------------------------------------
        $keyPair = $this->crypto->generateKeyPair((int) config('viafirma.crypto.key_size', 2048));
        $csrInput = $this->buildCsrInput($cr, $profile, $identityType, $countryCode, $cmd);
        $csrResult = $this->csrBuilderFactory->for($profile)->build($csrInput, $keyPair->privateKeyPem);

        // ── 5) Validar contra dnPattern (V-211) ----------------------------
        $this->dnValidator->assertMatches($csrResult->pem, $remoteProfile->dnPattern);

        // ── 6) Vault: persistir llave privada cifrada ----------------------
        $keyRef = $this->keyVault->store($keyPair->privateKeyPem, [
            'certificate_request_id' => $cr->id,
            'company_id'             => $cr->company_id,
            'profile'                => $profile->value,
        ]);

        // A partir de aquí la llave privada en memoria deja de ser útil; el
        // GC se la lleva al salir de scope. (No la pasamos a ningún logger.)
        unset($keyPair);

        try {
            // ── 7) Submit remoto ------------------------------------------
            $submitInput = new SubmitCsrInputDto(
                identityType:     $identityType,
                countryCode:      $countryCode,
                identity:         $this->resolveSubscriberIdentity($cr, $profile, $cmd),
                raCode:           $raCode,
                codProfile:       $remoteProfile->codProfile,
                emailCertificate: $cmd->emailCertificate,
                csrBase64:        $csrResult->base64,
                organizationType: $cmd->organizationType,
            );
            $submitResult = $this->client->submitCsr($submitInput);

            // ── 8-9) Persistir agregado + auditoría -----------------------
            return DB::transaction(function () use (
                $cr, $cmd, $profile, $identityType, $countryCode, $remoteProfile,
                $csrResult, $keyRef, $submitInput, $submitResult
            ) {
                $entity = $this->repository->create([
                    'certificate_request_id' => $cr->id,
                    'company_id'             => $cr->company_id,
                    'requested_by_user_id'   => $cmd->requestedByUserId,
                    'cod_request'            => $submitResult->codRequest,
                    'public_id'              => $submitResult->publicId,
                    'cod_profile'            => $remoteProfile->codProfile,
                    'ra_code'                => (string) config('viafirma.ra_code'),
                    'profile_type'           => $profile->value,
                    'identity_type'          => $identityType->value,
                    'country_code'           => $countryCode,
                    'organization_type'      => $cmd->organizationType?->value,
                    'validity_days'          => $remoteProfile->validity ?: (int) config('viafirma.certificate_validity_days', 730),
                    'internal_state'         => InternalState::SUBMITTED->value,
                    'remote_status'          => $submitResult->initialStatus,
                    'key_vault_ref'          => $keyRef,
                    'csr_fingerprint'        => $csrResult->fingerprint,
                    'csr_pem'                => $csrResult->pem,
                    'request_payload'        => $submitInput->toViafirmaPayload(),
                    'last_status_response'   => $submitResult->raw,
                    'submitted_at'           => now(),
                    'expires_at'             => Carbon::now()->addHours((int) config('viafirma.polling.expiration_hours', 72)),
                ]);

                // Historial técnico de la FSM
                ViafirmaStatusHistory::create([
                    'viafirma_certificate_request_id' => $entity->id,
                    'previous_state' => InternalState::DRAFT->value,
                    'new_state'      => InternalState::SUBMITTED->value,
                    'remote_status'  => $submitResult->initialStatus,
                    'raw_response'   => $submitResult->raw,
                    'attempt_number' => 0,
                    'occurred_at'    => now(),
                ]);

                // Auditoría de negocio (V-210)
                ChangeHistory::create([
                    'certificate_request_id' => $cr->id,
                    'status'                 => 'VIAFIRMA_SUBMITTED',
                    'comments'               => "Solicitud Viafirma enviada (codRequest={$submitResult->codRequest})",
                    'user_of_change'         => 'SYSTEM',
                    'user_id'                => $cmd->requestedByUserId,
                ]);

                // Despachar primer poll job (definición en Sprint 3 — clase fully-qualified
                // tolerada como string para no bloquear si aún no existe el job).
                if (class_exists(\App\Jobs\Viafirma\PollViafirmaStatusJob::class)) {
                    \App\Jobs\Viafirma\PollViafirmaStatusJob::dispatch($entity->id)
                        ->delay(now()->addSeconds(15));
                }

                $this->logger->info('viafirma.issue.success', [
                    'viafirma_id'   => $entity->id,
                    'cr_id'         => $cr->id,
                    'cod_request'   => $submitResult->codRequest,
                    'profile'       => $profile->value,
                ]);

                return $entity->fresh();
            });
        } catch (\Throwable $e) {
            // Si falla el submit, intentamos limpiar la llave huérfana para no
            // dejar material criptográfico colgado.
            $this->keyVault->destroy($keyRef);
            throw $e;
        }
    }

    // ── Helpers privados (resolución desde catálogo productivo) ────────────

    private function resolveProfile(CertificateRequest $cr): CertificateProfile
    {
        $org = $cr->organization;
        if ($org === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin type_organization asociado.");
        }
        return $this->profileTypeMapper->fromTypeOrganization($org);
    }

    private function resolveIdentityType(CertificateRequest $cr, CertificateProfile $profile): IdentityType
    {
        // identity_document_id SIEMPRE es el tipo de documento del representante legal
        // (CC, CE, etc.), tanto para PJ como para PN.
        // El NIT de la empresa está en el campo `dni`.
        $doc = $cr->identity;
        if ($doc === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin identity_document asociado.");
        }
        return $this->identityTypeMapper->fromIdentityDocument($doc);
    }

    private function enforceOrganizationTypeRule(CertificateProfile $profile, ?OrganizationType $orgType): void
    {
        if ($profile === CertificateProfile::FE_PJ && $orgType === null) {
            throw new ViafirmaException('organizationType es obligatorio para perfiles FE-PJ.');
        }
        if ($profile === CertificateProfile::FE_PN && $orgType !== null) {
            throw new ViafirmaException('organizationType NO debe enviarse para perfiles FE-PN.');
        }
    }

    /** @param ProfileDescriptor[] $remote */
    private function pickProfile(array $remote, CertificateProfile $profile): ProfileDescriptor
    {
        // Heurística: por defecto buscamos por nombre que contenga "PJ" o "PN".
        // En Sprint 2.5/3 esto se puede refinar con un mapping explícito en config.
        $needle = $profile === CertificateProfile::FE_PJ ? 'PJ' : 'PN';
        foreach ($remote as $p) {
            if (stripos($p->name, $needle) !== false || stripos($p->raw['code'] ?? '', $needle) !== false) {
                return $p;
            }
        }
        if ($remote === []) {
            throw new ViafirmaException("Viafirma no retornó perfiles disponibles para el RA configurado.");
        }
        // Fallback: si sólo hay uno, lo tomamos.
        if (count($remote) === 1) {
            return $remote[0];
        }
        throw new ViafirmaException("No se pudo seleccionar un perfil remoto que coincida con {$profile->value}.");
    }

    private function buildCsrInput(
        CertificateRequest $cr,
        CertificateProfile $profile,
        IdentityType $identityType,
        string $countryCode,
        IssueCertificateCommand $cmd,
    ): CsrInputDto {
        $company = $cr->company;
        if ($company === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin company.");
        }

        $department = $company->city?->department?->name_department ?? '';
        $cityName   = $company->city?->name_city ?? ($company->city_name ?? '');
        $street     = (string) ($company->address ?? $cr->address ?? '');

        if ($profile === CertificateProfile::FE_PJ) {
            return new CsrInputDto(
                profile:          $profile,
                country:          $countryCode,
                state:            mb_strtoupper($department),
                locality:         mb_strtoupper($cityName),
                street:           $street,
                serialNumber:     (string) ($company->dni ?? $cr->dni),
                email:            (string) ($company->email ?? $cmd->emailCertificate),
                givenName:        $this->firstName($cr->legal_representative ?? ''),
                surname:          $this->lastName($cr->legal_representative ?? ''),
                organization:     (string) ($cr->company_name ?? $company->company_name ?? ''),
                organizationUnit: (string) ($company->trade_name ?? $cr->company_name ?? $company->company_name ?? 'FACTURACION'),
                organizationType: $cmd->organizationType,
                emailCertificate: $cmd->emailCertificate,
                identity:         $cr->document_number,
            );
        }

        // FE_PN — API v3.4.53: sin O, OU, L, ST en el CSR
        return new CsrInputDto(
            profile:          $profile,
            country:          $countryCode,
            state:            null,     // API v3.4.53: FE-PN no lleva ST
            locality:         null,     // API v3.4.53: FE-PN no lleva L
            street:           $street,
            serialNumber:     (string) ($cr->document_number ?? $cr->dni),
            email:            (string) ($cr->email ?? $company->email ?? $cmd->emailCertificate),
            givenName:        $this->firstName($cr->legal_representative ?? ''),
            surname:          $this->lastName($cr->legal_representative ?? ''),
            emailCertificate: $cmd->emailCertificate,
            identity:         $cr->document_number,
        );
    }

    private function resolveSubscriberIdentity(
        CertificateRequest $cr,
        CertificateProfile $profile,
        IssueCertificateCommand $cmd,
    ): string {
        // document_number SIEMPRE es la cédula del representante legal.
        // Para PJ y PN es el mismo campo; el NIT de la empresa está en `dni`.
        return (string) ($cr->document_number ?? '');
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        return $parts[0] ?? '';
    }

    private function lastName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        if (count($parts) <= 1) {
            return '';
        }
        array_shift($parts);
        return implode(' ', $parts);
    }
}

