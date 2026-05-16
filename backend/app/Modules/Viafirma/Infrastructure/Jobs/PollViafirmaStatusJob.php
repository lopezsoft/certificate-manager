<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\Services\PollingScheduler;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\CircuitBreaker\ViafirmaCircuitBreaker;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;

/**
 * Job de polling auto-reagendable para una solicitud Viafirma (V-303).
 *
 * Flujo:
 *  1. Cargar entidad → guard (terminal, expired, max attempts, circuit breaker)
 *  2. GET /request/{cod}/status
 *  3. FSM transition (StateMachine)
 *  4. Persistir
 *  5. Decidir: auto-reagendar || despachar DownloadP7bJob (Sprint 4) || STOP
 *
 * ShouldBeUnique previene polling concurrente sobre la misma solicitud.
 * $tries = 1 porque el dominio controla los reintentos, no la cola.
 */
final class PollViafirmaStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 25;

    public function __construct(
        public readonly int $requestId,
    ) {}

    public function uniqueId(): string
    {
        return "viafirma-poll-{$this->requestId}";
    }

    public function uniqueFor(): int
    {
        return 600; // 10 minutos de lock
    }

    /**
     * Tags para Telescope / Horizon (cuando se instale).
     *
     * @return string[]
     */
    public function tags(): array
    {
        return ["viafirma:poll:{$this->requestId}"];
    }

    public function handle(
        ViafirmaClient $client,
        PollingScheduler $scheduler,
        StateMachine $fsm,
        ViafirmaCircuitBreaker $circuitBreaker,
        LoggerInterface $logger,
    ): void {
        $entity = ViafirmaCertificateRequest::find($this->requestId);

        if ($entity === null) {
            $logger->warning('viafirma.poll.entity_not_found', ['id' => $this->requestId]);
            return;
        }

        // Guard: estado terminal → no hacer nada
        if ($entity->isTerminal()) {
            $logger->info('viafirma.poll.skip_terminal', ['id' => $entity->id, 'state' => $entity->internal_state->value]);
            return;
        }

        // Guard: expirado → marcar EXPIRED
        if ($entity->hasExpired() || $scheduler->hasExceededSla($entity)) {
            $fsm->markExpired($entity);
            $entity->save();
            $logger->info('viafirma.poll.expired', ['id' => $entity->id]);
            return;
        }

        // Guard: máximo de intentos → marcar FAILED
        if ($scheduler->hasExceededMaxAttempts($entity)) {
            $fsm->markFailed($entity, 'MAX_ATTEMPTS', "Superado el máximo de {$entity->poll_attempts} intentos de polling.");
            $entity->save();
            $logger->info('viafirma.poll.max_attempts', ['id' => $entity->id, 'attempts' => $entity->poll_attempts]);
            return;
        }

        // Guard: circuit breaker abierto → reagendar corto sin llamar a Viafirma
        if ($circuitBreaker->isOpen()) {
            $delay = $scheduler->retryAfter($entity);
            self::dispatch($this->requestId)->delay(now()->addSeconds($delay));
            $entity->next_poll_at = now()->addSeconds($delay);
            $entity->save();
            $logger->info('viafirma.poll.circuit_open', ['id' => $entity->id, 'retry_in' => $delay]);
            return;
        }

        // Guard: cod_request vacío → no se puede consultar
        if (empty($entity->cod_request)) {
            $logger->warning('viafirma.poll.no_cod_request', ['id' => $entity->id]);
            return;
        }

        // ── Ejecutar polling ──────────────────────────────────────────────
        $entity->poll_attempts++;
        $entity->last_polled_at = now();

        try {
            $statusResult = $client->getStatus($entity->cod_request);
            $circuitBreaker->recordSuccess();
        } catch (TransientHttpException $e) {
            $circuitBreaker->recordFailure();
            $logger->warning('viafirma.poll.transient_error', [
                'id'      => $entity->id,
                'message' => $e->getMessage(),
            ]);

            $delay = $scheduler->retryAfter($entity);
            $entity->next_poll_at = now()->addSeconds($delay);
            $entity->save();

            self::dispatch($this->requestId)->delay(now()->addSeconds($delay));
            return;
        }

        // ── Transición FSM ────────────────────────────────────────────────
        $fsm->transition($entity, $statusResult->status, $statusResult->raw);

        // ── Decidir próximo paso ──────────────────────────────────────────
        if ($entity->isTerminal() || $entity->isReadyToDownload() || $entity->isFailed()) {
            // Sprint 4 escuchará ViafirmaReadyToDownload para despachar DownloadP7bJob
            $entity->next_poll_at = null;
            $entity->save();
            $logger->info('viafirma.poll.stopped', [
                'id'    => $entity->id,
                'state' => $entity->internal_state->value,
            ]);
            return;
        }

        // Auto-reagendar
        $delay = $scheduler->nextDelay($entity);
        $entity->next_poll_at = now()->addSeconds($delay);
        $entity->save();

        self::dispatch($this->requestId)->delay(now()->addSeconds($delay));

        $logger->info('viafirma.poll.rescheduled', [
            'id'       => $entity->id,
            'remote'   => $statusResult->status->value,
            'delay_s'  => $delay,
            'attempts' => $entity->poll_attempts,
        ]);
    }
}
