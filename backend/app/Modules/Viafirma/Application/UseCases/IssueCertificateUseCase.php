<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Models\FileManager;
use App\Modules\Viafirma\Application\Commands\IssueCertificateCommand;
use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrInputDto;
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
use App\Modules\Viafirma\Infrastructure\Jobs\PollViafirmaStatusJob;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * IssueCertificateUseCase — orquesta el ciclo completo de emisión:
 *
 *   1) Resolver perfil (FE_PJ/FE_PN) y datos del CSR desde catálogos productivos.
 *   2) Leer `ra_code` y `cod_profile` desde config (VIAFIRMA_RA_CODE / VIAFIRMA_COD_PROFILE).
 *   3) Generar par RSA-2048 LOCALMENTE.
 *   4) Construir el CSR con el builder correspondiente (Strategy).
 *   5) Guardar la llave privada cifrada en el KeyVault.
 *   6) `POST /request/fromCSR` → obtener codRequest + publicId.
 *   7) Persistir el agregado `viafirma_certificate_requests` (identidad) +
 *      `viafirma_certificate_request_states` (estado SUBMITTED).
 *   8) Auditoría: `change_histories` + `viafirma_status_history`.
 *   9) Despachar el primer poll job.
 *
 * NOTA: Tras la normalización, los datos de identidad van en viafirma_certificate_requests
 * y los datos de estado/ciclo de vida van en viafirma_certificate_request_states.
 * El repositorio crea ambos registros en create().
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
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(IssueCertificateCommand $cmd): ViafirmaCertificateRequest
    {
        $cr = CertificateRequest::query()
            ->with(['company.country', 'company.city.department', 'identity', 'organization'])
            ->findOrFail($cmd->certificateRequestId);

        // ── Validar relaciones críticas ───────────────────────────────────────
        // Todas estas relaciones debieron validarse en CreateCertificateRequestFormRequest.
        // Si llegamos aquí y alguna falta, es un bug upstream.
        $this->validateCertificateRequestStructure($cr);

        $existing = $this->repository->findByCertificateRequestId($cr->id);
        if ($existing !== null && !$existing->isFailed()) {
            throw new ViafirmaException(
                "La solicitud {$cr->id} ya tiene un trámite Viafirma en curso (id={$existing->id}, state={$existing->state?->internal_state?->value})."
            );
        }

        // ── 1) Resolver perfil + datos localmente ─────────────────────────
        $profile      = $this->resolveProfile($cr);
        $identityType = $cmd->identityTypeOverride ?? $this->resolveIdentityType($cr);
        $countryCode  = strtoupper((string) $cr->company->country->abbreviation_A2);

        $this->enforceOrganizationTypeRule($profile, $cmd->organizationType);

        // ── 2) Leer ra_code y cod_profile desde config (.env) ─────────────
        $raCode = (string) config('viafirma.ra_code');
        if ($raCode === '') {
            throw new ViafirmaException('config(viafirma.ra_code) está vacío — verifique VIAFIRMA_RA_CODE.');
        }

        $codProfile = $profile === CertificateProfile::FE_PJ
            ? (string) config('viafirma.cod_profile_corporate')
            : (string) config('viafirma.cod_profile_individual');

        if ($codProfile === '') {
            $envVar = $profile === CertificateProfile::FE_PJ
                ? 'VIAFIRMA_COD_PROFILE_CORPORATE'
                : 'VIAFIRMA_COD_PROFILE_INDIVIDUAL';
            throw new ViafirmaException(
                "config(viafirma.cod_profile_{$profile->value}) está vacío — verifique {$envVar}."
            );
        }

        $validityDays = (int) config('viafirma.certificate_validity_days', 730);

        $this->logger->info('viafirma.issue.start', [
            'cr_id'       => $cr->id,
            'profile'     => $profile->value,
            'ra_code'     => $raCode,
            'cod_profile' => substr($codProfile, 0, 12) . '…',
        ]);

        // ── 3-4) Generar llave + CSR ──────────────────────────────────────
        $this->logger->info('viafirma.issue.generating_key', ['cr_id' => $cr->id]);
        $keyPair   = $this->crypto->generateKeyPair((int) config('viafirma.crypto.key_size', 2048));
        $csrInput  = $this->buildCsrInput($cr, $profile, $identityType, $countryCode, $cmd);
        $csrResult = $this->csrBuilderFactory->for($profile)->build($csrInput, $keyPair->privateKeyPem);

        // ── 5) Vault: persistir llave privada cifrada ─────────────────────
        $keyRef = $this->keyVault->store($keyPair->privateKeyPem, [
            'certificate_request_id' => $cr->id,
            'company_id'             => $cr->company_id,
            'profile'                => $profile->value,
        ]);

        unset($keyPair);

        try {
            // ── 6) Submit remoto ──────────────────────────────────────────
            $this->logger->info('viafirma.issue.submitting_csr', [
                'cr_id'       => $cr->id,
                'profile'     => $profile->value,
                'cod_profile' => substr($codProfile, 0, 12) . '…',
            ]);

            $submitInput = new SubmitCsrInputDto(
                identityType:     $identityType,
                countryCode:      $countryCode,
                identity:         $this->resolveSubscriberIdentity($cr),
                raCode:           $raCode,
                codProfile:       $codProfile,
                emailCertificate: $cmd->emailCertificate,
                csrBase64:        $csrResult->base64,
                organizationType: $profile === CertificateProfile::FE_PJ ? $cmd->organizationType : null,
            );
            $submitResult = $this->client->submitCsr($submitInput);

            // ── 7-8) Persistir agregado + auditoría ───────────────────────
            return DB::transaction(function () use (
                $cr, $cmd, $profile, $identityType, $countryCode, $codProfile, $raCode,
                $csrResult, $keyRef, $submitInput, $submitResult, $validityDays
            ) {
                // El repositorio crea viafirma_certificate_requests (identidad)
                // y viafirma_certificate_request_states (estado) en una sola llamada.
                $entity = $this->repository->create([
                    // ── Identidad ──────────────────────────────────────────
                    'certificate_request_id' => $cr->id,
                    'company_id'             => $cr->company_id,
                    'requested_by_user_id'   => $cmd->requestedByUserId,
                    'cod_request'            => $submitResult->codRequest,
                    'public_id'              => $submitResult->publicId,
                    'cod_profile'            => $codProfile,
                    'ra_code'                => $raCode,
                    'profile_type'           => $profile->value,
                    'identity_type'          => $identityType->value,
                    'country_code'           => $countryCode,
                    'organization_type'      => $cmd->organizationType?->value,
                    'validity_days'          => $validityDays,
                    // ── Estado (va a viafirma_certificate_request_states) ──
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

                ViafirmaStatusHistory::create([
                    'viafirma_certificate_request_id' => $entity->id,
                    'previous_state' => InternalState::DRAFT->value,
                    'new_state'      => InternalState::SUBMITTED->value,
                    'remote_status'  => $submitResult->initialStatus,
                    'raw_response'   => $submitResult->raw,
                    'attempt_number' => 0,
                    'occurred_at'    => now(),
                ]);

                ChangeHistory::create([
                    'certificate_request_id' => $cr->id,
                    'status'                 => CertificateRequestStatusEnum::PROCESSING->value,
                    'comments'               => 'Solicitud de certificado enviada al proveedor para emisión automática.',
                    'user_of_change'         => 'SYSTEM',
                    'user_id'                => $cmd->requestedByUserId,
                ]);

                // ── Registrar referencia de llave privada en file_managers ────────────────────
                FileManager::create([
                    'certificate_request_id' => $cr->id,
                    'file_path'              => 'vault://' . $keyRef,
                    'file_name'              => 'private_key_reference',
                    'extension_file'         => 'key',
                    'mime_type'              => 'application/x-pkcs12-key',
                    'document_type'          => 'PRIVATE_KEY',
                    'file_size'              => 0,
                    'status'                 => 'ACTIVE',
                ]);

                // Primer poll job — 15 s de delay para dar tiempo a que Viafirma procese
                PollViafirmaStatusJob::dispatch($entity->id)
                    ->delay(now()->addSeconds(15));

                $this->logger->info('viafirma.issue.success', [
                    'viafirma_id' => $entity->id,
                    'cr_id'       => $cr->id,
                    'cod_request' => $submitResult->codRequest,
                    'profile'     => $profile->value,
                ]);

                return $entity->fresh(['state']);
            });
        } catch (\Throwable $e) {
            $this->keyVault->destroy($keyRef);
            throw $e;
        }
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Valida que CertificateRequest tenga todas las relaciones y campos críticos.
     * Si alguno falta, es un bug de validación en CreateCertificateRequestFormRequest.
     */
    private function validateCertificateRequestStructure(CertificateRequest $cr): void
    {
        if ($cr->company === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin company asociada.");
        }

        if ($cr->company->country === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id}: company sin country asociado.");
        }

        if ($cr->company->city === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id}: company sin city asociado.");
        }

        if ($cr->company->city->department === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id}: company->city sin department asociado.");
        }

        if ($cr->identity === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin identity_document asociado.");
        }

        if ($cr->organization === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin type_organization asociado.");
        }

        // Campos escalares requeridos (validados en FormRequest)
        if (empty($cr->company_name)) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin company_name.");
        }

        if (empty($cr->document_number)) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin document_number.");
        }

        if (empty($cr->legal_rep_email)) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin legal_rep_email.");
        }

        if (empty($cr->legal_rep_first_name) || empty($cr->legal_rep_last_name)) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin legal_rep_first_name o legal_rep_last_name.");
        }

        if (empty($cr->address)) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin address.");
        }

        if (empty($cr->dni)) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin dni.");
        }
    }

    private function resolveProfile(CertificateRequest $cr): CertificateProfile
    {
        $org = $cr->organization;
        if ($org === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin type_organization asociado.");
        }
        return $this->profileTypeMapper->fromTypeOrganization($org);
    }

    private function resolveIdentityType(CertificateRequest $cr): IdentityType
    {
        $doc = $cr->identity;
        if ($doc === null) {
            throw new ViafirmaException("CertificateRequest {$cr->id} sin identity_document asociado.");
        }
        return $this->identityTypeMapper->fromIdentityDocument($doc);
    }

    /**
     * FE-PJ requiere organizationType; FE-PN NO debe llevarlo.
     */
    private function enforceOrganizationTypeRule(CertificateProfile $profile, ?OrganizationType $orgType): void
    {
        if ($profile === CertificateProfile::FE_PJ && $orgType === null) {
            throw new ViafirmaException('organizationType es obligatorio para perfiles FE-PJ.');
        }
        if ($profile === CertificateProfile::FE_PN && $orgType !== null) {
            throw new ViafirmaException('organizationType NO debe enviarse para perfiles FE-PN.');
        }
    }

    private function buildCsrInput(
        CertificateRequest $cr,
        CertificateProfile $profile,
        IdentityType $identityType,
        string $countryCode,
        IssueCertificateCommand $cmd,
    ): CsrInputDto {
        // Todos los datos vienen de CertificateRequest, ya validados en CreateCertificateRequestFormRequest.
        // No hay fallbacks a company ni lógica defensiva — si algo falta, es un bug upstream.
        $department = mb_strtoupper((string) $cr->company->city->department->name_department);
        $cityName   = mb_strtoupper((string) $cr->company->city->name_city);
        $street     = (string) $cr->address;

        // Ambos perfiles (FE_PJ y FE_PN) requieren nombres y apellidos separados,
        // ya consolidados en legal_rep_first_name/last_name desde FormRequest.
        $givenName = (string) $cr->legal_rep_first_name;
        $surname   = (string) $cr->legal_rep_last_name;
        $email     = (string) $cr->legal_rep_email;

        if ($profile === CertificateProfile::FE_PJ) {
            return new CsrInputDto(
                profile:          $profile,
                country:          $countryCode,
                state:            $department,
                locality:         $cityName,
                street:           $street,
                serialNumber:     (string) $cr->dni,
                email:            $email,
                givenName:        $givenName,
                surname:          $surname,
                organization:     (string) $cr->company_name,
                organizationUnit: (string) $cr->company_name,
                organizationType: $cmd->organizationType,
                emailCertificate: $cmd->emailCertificate,
                identity:         (string) $cr->document_number,
            );
        }

        // FE_PN — mismo conjunto de atributos que FE_PJ (nombres ya consolidados)
        return new CsrInputDto(
            profile:          $profile,
            country:          $countryCode,
            state:            $department,
            locality:         $cityName,
            street:           $street,
            serialNumber:     (string) $cr->dni,
            email:            $email,
            givenName:        $givenName,
            surname:          $surname,
            emailCertificate: $cmd->emailCertificate,
            identity:         (string) $cr->document_number,
        );
    }

    private function resolveSubscriberIdentity(CertificateRequest $cr): string
    {
        return (string) $cr->document_number;
    }
}
