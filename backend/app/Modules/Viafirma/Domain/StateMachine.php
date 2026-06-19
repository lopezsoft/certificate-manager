<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Events\ViafirmaReadyToDownload;
use App\Modules\Viafirma\Domain\Events\ViafirmaRequestFailed;
use App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Máquina de estados finita del bounded context Viafirma (V-301).
 *
 * Responsabilidades:
 *  1. Mapear estado remoto Viafirma → InternalState (via RemoteStatus::toInternalState)
 *  2. Validar guard clauses (no retroceder, no transicionar desde terminal)
 *  3. Registrar cada transición en viafirma_status_history
 *  4. Disparar eventos de dominio para desacoplar side-effects
 *
 * NO modifica campos de polling (eso lo hace el job).
 * NO persiste la entidad (eso lo hace el caller).
 *
 * NOTA: Tras la normalización, los campos de estado se encuentran en
 * $entity->state (ViafirmaCertificateRequestState). La FSM muta ese objeto.
 */
final class StateMachine
{
    public function __construct(
        private readonly SafePemLogger $logger,
    ) {}

    /**
     * Aplica una transición de estado a la entidad basándose en el estado remoto.
     *
     * @param ViafirmaCertificateRequest $entity  Entidad (se muta in-place via $entity->state)
     * @param RemoteStatus              $remote  Estado remoto reportado por Viafirma
     * @param array                     $rawResponse  Payload crudo (auditoría)
     *
     * @return bool true si hubo cambio de internal_state, false si el estado no cambió
     */
    public function transition(
        ViafirmaCertificateRequest $entity,
        RemoteStatus $remote,
        array $rawResponse = [],
    ): bool {
        $state = $entity->state;

        $previousInternal = $state->internal_state;
        $previousRemote   = $state->remote_status;

        // Guard: no transicionar desde estado terminal
        if ($entity->isTerminal()) {
            $this->logger->info('viafirma.fsm.skip_terminal', [
                'id'    => $entity->id,
                'state' => $previousInternal->value,
            ]);
            return false;
        }

        $newInternal = $remote->toInternalState();

        // Actualizar estado remoto siempre (el remoto es informativo)
        $state->remote_status        = $remote->value;
        $state->last_status_response = $rawResponse;

        // Determinar si hay cambio de internal_state
        $stateChanged = $previousInternal !== $newInternal;

        if ($stateChanged) {
            $state->internal_state = $newInternal;

            // Registrar errores si aplica
            if ($newInternal->isFailureLike()) {
                $state->last_error_code    = $remote->value;
                $state->last_error_message = $this->buildErrorMessage($remote);
            }

            $this->logger->info('viafirma.fsm.transition', [
                'id'     => $entity->id,
                'from'   => $previousInternal->value,
                'to'     => $newInternal->value,
                'remote' => $remote->value,
            ]);
        }

        // Registrar en historial siempre (incluso si solo cambió remote_status)
        $this->recordHistory($entity, $previousInternal, $newInternal, $remote, $rawResponse);

        // Disparar eventos de dominio si hubo cambio
        if ($stateChanged) {
            $this->dispatchDomainEvents($entity, $previousInternal, $newInternal, $remote);
        }

        return $stateChanged;
    }

    /**
     * Transición manual a FAILED (por timeout, circuit breaker, etc.)
     */
    public function markFailed(
        ViafirmaCertificateRequest $entity,
        string $errorCode,
        string $errorMessage,
    ): void {
        $state    = $entity->state;
        $previous = $state->internal_state;

        if ($entity->isTerminal()) {
            return;
        }

        $state->internal_state     = InternalState::FAILED;
        $state->last_error_code    = $errorCode;
        $state->last_error_message = $errorMessage;

        $this->recordHistory(
            $entity,
            $previous,
            InternalState::FAILED,
            null,
            ['manual_error' => $errorCode, 'message' => $errorMessage],
        );

        event(new ViafirmaRequestFailed($entity, $errorCode, $errorMessage));

        $this->logger->warning('viafirma.fsm.marked_failed', [
            'id'   => $entity->id,
            'code' => $errorCode,
        ]);
    }

    /**
     * Transición manual a EXPIRED (SLA superado).
     */
    public function markExpired(ViafirmaCertificateRequest $entity): void
    {
        $state    = $entity->state;
        $previous = $state->internal_state;

        if ($entity->isTerminal()) {
            return;
        }

        $state->internal_state     = InternalState::EXPIRED;
        $state->last_error_code    = 'POLL_EXPIRED';
        $state->last_error_message = 'SLA de acreditación superado (72h).';

        $this->recordHistory(
            $entity,
            $previous,
            InternalState::EXPIRED,
            null,
            ['reason' => 'expiration_hours_exceeded'],
        );

        event(new ViafirmaRequestFailed($entity, 'POLL_EXPIRED', $state->last_error_message));

        $this->logger->warning('viafirma.fsm.expired', ['id' => $entity->id]);
    }

    // ── Privados ──────────────────────────────────────────────────────────

    private function recordHistory(
        ViafirmaCertificateRequest $entity,
        InternalState $previousState,
        InternalState $newState,
        ?RemoteStatus $remote,
        array $rawResponse,
    ): void {
        ViafirmaStatusHistory::create([
            'viafirma_certificate_request_id' => $entity->id,
            'previous_state'                  => $previousState->value,
            'new_state'                       => $newState->value,
            'remote_status'                   => $remote?->value,
            'raw_response'                    => $rawResponse ?: null,
            'attempt_number'                  => $entity->state->poll_attempts,
            'occurred_at'                     => now(),
        ]);
    }

    private function dispatchDomainEvents(
        ViafirmaCertificateRequest $entity,
        InternalState $previous,
        InternalState $new,
        RemoteStatus $remote,
    ): void {
        event(new ViafirmaStatusChanged($entity, $previous, $new, $remote));

        if ($new === InternalState::READY_TO_DOWNLOAD) {
            event(new ViafirmaReadyToDownload($entity));
        }

        if ($new->isFailureLike()) {
            event(new ViafirmaRequestFailed(
                $entity,
                $entity->state->last_error_code ?? $remote->value,
                $entity->state->last_error_message ?? 'Estado remoto: ' . $remote->value,
            ));
        }
    }

    private function buildErrorMessage(RemoteStatus $remote): string
    {
        return match ($remote) {
            RemoteStatus::RUES_ERROR             => 'Error en validación RUES. Requiere intervención del operador RA.',
            RemoteStatus::ACCREDITATION_REJECTED  => 'Acreditación KYC rechazada por el operador RA.',
            RemoteStatus::FAIL                    => 'Viafirma reportó fallo terminal en el trámite.',
            default                               => 'Estado remoto: ' . $remote->value,
        };
    }
}
