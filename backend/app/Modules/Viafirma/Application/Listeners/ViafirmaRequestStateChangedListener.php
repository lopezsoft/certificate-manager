<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Listeners;

use App\Enums\CertificateRequestStatusEnum;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Listener que sincroniza cambios de estado de Viafirma al campo visible `request_status`.
 *
 * Responsabilidades:
 * - Cuando InternalState cambia a FAILED (rechazo definitivo):
 *   → Escribir automáticamente request_status = REJECTED
 * - Cuando InternalState cambia a REVOKED/EXPIRED:
 *   → Mantener la sincronización existente con request_status
 *
 * IMPORTANTE: FAILED_RECOVERABLE (errores recuperables como rues_error) NO dispara
 * cambio a REJECTED. Permanecen como PROCESSING mientras el proveedor los resuelve.
 *
 * V-312: Sincronización automática de request_status en fallos definitivos.
 */
final class ViafirmaRequestStateChangedListener
{
    public function __construct(
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(ViafirmaStatusChanged $event): void
    {
        $certificateRequest = $event->entity->certificateRequest;

        if ($certificateRequest === null) {
            $this->logger->warning('viafirma.listener.certificate_request_not_found', [
                'viafirma_request_id' => $event->entity->id,
            ]);
            return;
        }

        $newState = $event->newState;

        // Solo InternalState::FAILED dispara cambio automático a REJECTED
        // FAILED_RECOVERABLE permanece como PROCESSING (es recuperable)
        if ($newState === InternalState::FAILED) {
            $this->syncFailedToRejected($certificateRequest, $event);
        }

        // REVOKED y EXPIRED se sincronizan automáticamente (ya ocurría antes)
        if ($newState === InternalState::REVOKED) {
            $this->syncRevokedStatus($certificateRequest, $event);
        }

        if ($newState === InternalState::EXPIRED) {
            $this->syncExpiredStatus($certificateRequest, $event);
        }
    }

    private function syncFailedToRejected($certificateRequest, ViafirmaStatusChanged $event): void
    {
        $oldStatus = $certificateRequest->request_status;

        // Validar transición permitida
        if (!CertificateRequestStatusEnum::canTransitionTo($oldStatus, CertificateRequestStatusEnum::REJECTED->value)) {
            $this->logger->warning('viafirma.listener.invalid_transition_to_rejected', [
                'certificate_request_id' => $certificateRequest->id,
                'current_status'         => $oldStatus,
                'viafirma_request_id'    => $event->entity->id,
                'remote_status'          => $event->entity->state->remote_status,
            ]);
            return;
        }

        // Actualizar status
        $certificateRequest->update([
            'request_status' => CertificateRequestStatusEnum::REJECTED->value,
        ]);

        $this->logger->info('viafirma.listener.auto_reject', [
            'certificate_request_id' => $certificateRequest->id,
            'viafirma_request_id'    => $event->entity->id,
            'previous_status'        => $oldStatus,
            'new_status'             => CertificateRequestStatusEnum::REJECTED->value,
            'remote_status'          => $event->entity->state->remote_status,
            'triggered_by'           => 'ViafirmaRequestStateChangedListener',
            'reason'                 => 'InternalState::FAILED from remote status: ' . $event->entity->state->remote_status,
        ]);
    }

    private function syncRevokedStatus($certificateRequest, ViafirmaStatusChanged $event): void
    {
        $certificateRequest->update([
            'request_status' => CertificateRequestStatusEnum::REVOKED->value,
        ]);

        $this->logger->info('viafirma.listener.auto_revoke', [
            'certificate_request_id' => $certificateRequest->id,
            'viafirma_request_id'    => $event->entity->id,
            'triggered_by'           => 'ViafirmaRequestStateChangedListener',
        ]);
    }

    private function syncExpiredStatus($certificateRequest, ViafirmaStatusChanged $event): void
    {
        $certificateRequest->update([
            'request_status' => CertificateRequestStatusEnum::EXPIRED->value,
        ]);

        $this->logger->info('viafirma.listener.auto_expire', [
            'certificate_request_id' => $certificateRequest->id,
            'viafirma_request_id'    => $event->entity->id,
            'triggered_by'           => 'ViafirmaRequestStateChangedListener',
        ]);
    }
}
