<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Registra que el navegador del cliente llegó al callback público tras
 * completar (o abandonar) el flujo de verificación de identidad en MetaMap.
 *
 * IMPORTANTE: esto es únicamente una señal de UX/analytics — confirma que el
 * navegador del cliente terminó de navegar por MetaMap, NO que Viafirma
 * aprobó la identidad. La única fuente de verdad de la aprobación real sigue
 * siendo el polling vía GET /request/{codRequest}/status. Este use case NO
 * transiciona la FSM ni el `internal_state`.
 *
 * Depende únicamente del contrato del repositorio (no de Eloquent directo)
 * para poder testearse con un mock, sin tocar la base de datos.
 */
class RecordKycFlowCompletedUseCase
{
    public function __construct(
        private readonly ViafirmaCertificateRequestRepositoryContract $repository,
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(string $publicId, ?string $ip, ?string $userAgent): ?ViafirmaCertificateRequest
    {
        $entity = $this->repository->findByPublicId($publicId);

        if ($entity === null || $entity->state === null) {
            $this->logger->warning('viafirma.kyc_callback.not_found', ['public_id' => $publicId]);
            return null;
        }

        $entity->state->kyc_flow_completed_at         = now();
        $entity->state->kyc_flow_completed_ip         = $ip;
        $entity->state->kyc_flow_completed_user_agent = $userAgent !== null ? substr($userAgent, 0, 500) : null;
        $entity->state->save();

        $this->logger->info('viafirma.kyc_callback.recorded', [
            'id'        => $entity->id,
            'public_id' => $publicId,
            'ip'        => $ip,
        ]);

        return $entity;
    }
}
