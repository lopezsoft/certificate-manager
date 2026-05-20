<?php

declare(strict_types=1);

namespace App\Services\Certificate\Providers;

use App\Contracts\CertificateIssuanceProvider;
use App\DTOs\Certificate\IssuanceRequest;
use App\DTOs\Certificate\IssuanceResult;
use App\Exceptions\Certificate\CertificateIssuanceException;
use App\Modules\Viafirma\Application\Commands\IssueCertificateCommand;
use App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Psr\Log\LoggerInterface;

/**
 * Proveedor Viafirma RA — flujo Zero-Touch PKCS#10.
 *
 * Encapsula el {@see IssueCertificateUseCase} y la lectura del agregado
 * `viafirma_certificate_requests` detrás del contrato agnóstico
 * {@see CertificateIssuanceProvider}.
 *
 * No expone ninguna ruta propia: la capa HTTP llama al orquestador, que
 * delega aquí. De este modo el cliente no necesita saber que el backend
 * está hablando con Viafirma.
 */
final class ViafirmaIssuanceProvider implements CertificateIssuanceProvider
{
    public const NAME = 'viafirma';

    public function __construct(
        private readonly IssueCertificateUseCase $useCase,
        private readonly ViafirmaCertificateRequestRepositoryContract $repository,
        private readonly LoggerInterface $logger,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(IssuanceRequest $request): bool
    {
        // 1) Feature flag global del módulo.
        if (!(bool) config('viafirma.feature_flag.enabled', false)) {
            return false;
        }

        // 2) Email del certificado es obligatorio para iniciar el flujo.
        if (empty($request->emailCertificate)) {
            return false;
        }

        // 3) No debe existir otro trámite Viafirma activo para la solicitud.
        $existing = $this->repository->findByCertificateRequestId($request->certificateRequestId);
        if ($existing !== null && !$existing->isFailed()) {
            return false;
        }

        return true;
    }

    public function issue(IssuanceRequest $request): IssuanceResult
    {
        $command = new IssueCertificateCommand(
            certificateRequestId: $request->certificateRequestId,
            requestedByUserId:    $request->requestedByUserId,
            emailCertificate:     (string) $request->emailCertificate,
            organizationType:     $request->organizationType !== null
                ? OrganizationType::from($request->organizationType)
                : null,
            identityTypeOverride: $request->identityTypeOverride !== null
                ? IdentityType::from($request->identityTypeOverride)
                : null,
        );

        try {
            $entity = $this->useCase->handle($command);

            return new IssuanceResult(
                providerName: self::NAME,
                status:       IssuanceResult::STATUS_SUBMITTED,
                message:      'Solicitud Viafirma creada exitosamente.',
                externalId:   $entity->cod_request,
                resourceId:   $entity->id,
                httpStatus:   201,
                data:         [
                    'public_id'      => $entity->public_id,
                    'profile_type'   => $entity->profile_type,
                    'identity_type'  => $entity->identity_type,
                    'internal_state' => $entity->internal_state?->value,
                    'remote_status'  => $entity->remote_status,
                    'submitted_at'   => optional($entity->submitted_at)?->toISOString(),
                    'expires_at'     => optional($entity->expires_at)?->toISOString(),
                ],
            );
        } catch (ViafirmaException $e) {
            $this->logger->warning('certificate.issuance.viafirma.domain_error', [
                'cr_id'   => $request->certificateRequestId,
                'message' => $e->getMessage(),
            ]);
            $code = str_contains($e->getMessage(), 'ya tiene un trámite') ? 409 : 422;
            throw new CertificateIssuanceException($e->getMessage(), $code, self::NAME, $e);
        } catch (TransientHttpException $e) {
            $this->logger->error('certificate.issuance.viafirma.transient_error', [
                'cr_id'   => $request->certificateRequestId,
                'message' => $e->getMessage(),
            ]);
            throw new CertificateIssuanceException(
                'Error de comunicación con Viafirma RA. Intente nuevamente.',
                502,
                self::NAME,
                $e,
            );
        } catch (ViafirmaClientException $e) {
            $this->logger->error('certificate.issuance.viafirma.client_error', [
                'cr_id'   => $request->certificateRequestId,
                'message' => $e->getMessage(),
            ]);
            throw new CertificateIssuanceException(
                'Error del servicio Viafirma: ' . $e->getMessage(),
                422,
                self::NAME,
                $e,
            );
        }
    }

    public function status(int $certificateRequestId): IssuanceResult
    {
        $entity = $this->repository->findByCertificateRequestId($certificateRequestId);

        if ($entity === null) {
            return new IssuanceResult(
                providerName: self::NAME,
                status:       IssuanceResult::STATUS_UNSUPPORTED,
                message:      'No existe un trámite Viafirma para esta solicitud.',
                resourceId:   $certificateRequestId,
                httpStatus:   404,
            );
        }

        $entity->load(['certificateRequest', 'company', 'statusHistory']);

        return new IssuanceResult(
            providerName: self::NAME,
            status:       $this->mapInternalStateToStatus($entity),
            message:      'Estado del trámite Viafirma.',
            externalId:   $entity->cod_request,
            resourceId:   $entity->id,
            data:         [
                'public_id'      => $entity->public_id,
                'profile_type'   => $entity->profile_type,
                'identity_type'  => $entity->identity_type,
                'internal_state' => $entity->internal_state?->value,
                'remote_status'  => $entity->remote_status,
                'submitted_at'   => optional($entity->submitted_at)?->toISOString(),
                'expires_at'     => optional($entity->expires_at)?->toISOString(),
                'history_count'  => $entity->statusHistory?->count() ?? 0,
            ],
        );
    }

    /**
     * Mapea estados internos Viafirma a los estados normalizados del DTO.
     */
    private function mapInternalStateToStatus(ViafirmaCertificateRequest $entity): string
    {
        $state = $entity->internal_state?->value;

        return match ($state) {
            'COMPLETED', 'ASSEMBLED' => IssuanceResult::STATUS_READY,
            'FAILED', 'EXPIRED'      => IssuanceResult::STATUS_FAILED,
            'DRAFT'                  => IssuanceResult::STATUS_PROCESSING,
            default                  => IssuanceResult::STATUS_PROCESSING,
        };
    }
}

