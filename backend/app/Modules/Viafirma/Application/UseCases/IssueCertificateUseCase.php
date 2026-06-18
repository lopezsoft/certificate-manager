<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
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
use Psr\Log\LoggerInterface;

/**
 * IssueCertificateUseCase — orquesta el ciclo completo de emisión:
 *
 *   1) Resolver perfil (FE_PJ/FE_PN) y datos del CSR desde catálogos productivos.
 *   2) Leer `ra_code` y `cod_profile` desde config (VIAFIRMA_RA_CODE / VIAFIRMA_COD_PROFILE).
 *      El endpoint GET /ra/available-profiles solo se llama UNA VEZ de forma manual
 *      para obtener estos valores, los cuales se persisten en .env.
 *   3) Generar par RSA-2048 LOCALMENTE.
 *   4) Construir el CSR con el builder correspondiente (Strategy).
 *   5) Guardar la llave privada cifrada en el KeyVault.
 *   6) `POST /request/fromCSR` → obtener codRequest + publicId (ambos directos, API v3.4.53).
 *      El campo `csr` lleva el PEM COMPLETO codificado en base64 (con headers BEGIN/END).
 *   7) Persistir el agregado `viafirma_certificate_requests` (estado SUBMITTED).
 *   8) Auditoría: `change_histories` + `viafirma_status_history`.
 *   9) Despachar el primer poll job.
 *
 *  Payload FE-PJ: identityType, countryCode, identity, ra, codProfile,
 *                 emailCertificate, organizationType, csr
 *  Payload FE-PN: identityType, countryCode, identity, ra, codProfile,
 *                 emailCertificate, csr  (sin organizationType)
 *
 *  Las llamadas HTTP (submitCsr) ocurren FUERA de la transacción de BD para no
 *  mantener locks durante I/O de red. Solo los writes a BD (pasos 7-8) van en
 *  transacción. Si submitCsr falla, la llave del vault se destruye.
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

        // ── 1) Resolver perfil + datos localmente ─────────────────────────
        $profile      = $this->resolveProfile($cr);
        $identityType = $cmd->identityTypeOverride ?? $this->resolveIdentityType($cr, $profile);
        $countryCode  = strtoupper((string) ($cr->company?->country?->abbreviation_A2 ?? 'CO'));

        $this->enforceOrganizationTypeRule($profile, $cmd->organizationType);

        // ── 2) Leer ra_code y cod_profile desde config (.env) ─────────────
        // El endpoint GET /ra/available-profiles se ejecuta UNA SOLA VEZ de
        // forma manual para obtener estos valores y persistirlos en .env.
        // No hay necesidad de llamarlo en cada emisión.
        $raCode = (string) config('viafirma.ra_code');
        if ($raCode === '') {
            throw new ViafirmaException('config(viafirma.ra_code) está vacío — verifique VIAFIRMA_RA_CODE.');
        }

        // Seleccionar el cod_profile según el tipo de persona:
        //   FE_PJ (Persona Jurídica)  → VIAFIRMA_COD_PROFILE_CORPORATE
        //   FE_PN (Persona Natural)   → VIAFIRMA_COD_PROFILE_INDIVIDUAL
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
            // FE-PJ: incluye organizationType en el payload.
            // FE-PN: organizationType = null → no se envía (ver SubmitCsrInputDto::toViafirmaPayload).
            // El campo `csr` lleva el PEM completo codificado en base64 (con headers BEGIN/END).
            $this->logger->info('viafirma.issue.submitting_csr', [
                'cr_id'      => $cr->id,
                'profile'    => $profile->value,
                'cod_profile' => substr($codProfile, 0, 12) . '…',
            ]);

            $submitInput = new SubmitCsrInputDto(
                identityType:     $identityType,
                countryCode:      $countryCode,
                identity:         $this->resolveSubscriberIdentity($cr, $profile, $cmd),
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
                $entity = $this->repository->create([
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

                // Primer poll job — 15 s de delay para dar tiempo a que Viafirma procese
                PollViafirmaStatusJob::dispatch($entity->id)
                    ->delay(now()->addSeconds(15));

                $this->logger->info('viafirma.issue.success', [
                    'viafirma_id' => $entity->id,
                    'cr_id'       => $cr->id,
                    'cod_request' => $submitResult->codRequest,
                    'profile'     => $profile->value,
                ]);

                return $entity->fresh();
            });
        } catch (\Throwable $e) {
            $this->keyVault->destroy($keyRef);
            throw $e;
        }
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

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
                givenName:        (string) ($cr->legal_rep_first_name ?? $this->firstName($cr->legal_representative ?? '')),
                surname:          (string) ($cr->legal_rep_last_name ?? $this->lastName($cr->legal_representative ?? '')),
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
            serialNumber:     (string) ($cr->dni),
            email:            (string) ($cr->email ?? $company->email ?? $cmd->emailCertificate),
            givenName:        (string) ($cr->legal_rep_first_name ?? $this->firstName($cr->legal_representative ?? '')),
            surname:          (string) ($cr->legal_rep_last_name ?? $this->lastName($cr->legal_representative ?? '')),
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

