<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\Services\PollingScheduler;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Polling de estado Viafirma — se auto-reprograma cada ~60 s hasta resolverse.
 *
 * Flujo:
 *   1. GET /request/{codRequest}/status
 *   2. FSM actualiza internal_state + remote_status (en $entity->state)
 *   3. Si estado = Generated_Not_Downloaded → despacha DownloadP7bJob (descarga)
 *   4. Si estado terminal o fallo → detiene polling
 *   5. Si sigue en curso → reprograma a los 60 s
 *
 * ShouldBeUnique evita que el mismo job se encole dos veces simultáneamente.
 * Adicionalmente se usa un mutex de Cache para proteger la sección crítica
 * de escritura en BD contra race conditions entre workers concurrentes
 * (ej: ReviveStalledViafirmaPollsJob + auto-reprogramación simultánea).
 */
final class PollViafirmaStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 1;   // El dominio controla los reintentos, no la cola
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
        return 120; // lock de 2 min — suficiente para el timeout + margen
    }

    /** @return string[] */
    public function tags(): array
    {
        return ["viafirma:poll:{$this->requestId}"];
    }

    public function handle(
        ViafirmaClient $client,
        PollingScheduler $scheduler,
        StateMachine $fsm,
        SafePemLogger $logger,
    ): void {
        // ── Mutex distribuido: protege la sección crítica contra race conditions ──
        $mutexKey = "viafirma:poll:mutex:{$this->requestId}";
        $lock = Cache::lock($mutexKey, 30);

        if (!$lock->get()) {
            $logger->info('viafirma.poll.mutex_busy', [
                'id'   => $this->requestId,
                'hint' => 'Otro worker ya está procesando este poll — se reprograma en 10s.',
            ]);
            self::dispatch($this->requestId)->delay(now()->addSeconds(10));
            return;
        }

        try {
            $this->executePolling($client, $scheduler, $fsm, $logger);
        } finally {
            $lock->release();
        }
    }

    /**
     * Si el job falla (excepción no controlada, timeout, etc.) tras agotar $tries,
     * la cadena de auto-reprogramación (que solo ocurre dentro de executePolling)
     * se corta. Sin este hook, la única red de seguridad sería el watchdog de
     * ReviveStalledViafirmaPollsJob, que solo revive huérfanas tras 20 min —
     * tiempo suficiente para perder la ventana de captura del link KYC.
     */
    public function failed(\Throwable $exception): void
    {
        app(SafePemLogger::class)->warning('viafirma.poll.job_failed_rescheduling', [
            'id'    => $this->requestId,
            'error' => $exception->getMessage(),
        ]);

        self::dispatch($this->requestId)->delay(now()->addSeconds(30));
    }

    /**
     * Lógica de polling extraída para mantener handle() limpio.
     */
    private function executePolling(
        ViafirmaClient $client,
        PollingScheduler $scheduler,
        StateMachine $fsm,
        SafePemLogger $logger,
    ): void {
        $entity = ViafirmaCertificateRequest::with('state')->find($this->requestId);

        if ($entity === null) {
            $logger->warning('viafirma.poll.entity_not_found', ['id' => $this->requestId]);
            return;
        }

        $state = $entity->state;

        // Guard: estado terminal → no hacer nada más
        if ($entity->isTerminal()) {
            $logger->info('viafirma.poll.skip_terminal', [
                'id'    => $entity->id,
                'state' => $state->internal_state->value,
            ]);
            return;
        }

        // Guard: sin codRequest → no se puede consultar
        if (empty($entity->cod_request)) {
            $logger->warning('viafirma.poll.no_cod_request', ['id' => $entity->id]);
            return;
        }

        // ── Consultar estado en Viafirma ──────────────────────────────────
        try {
            $statusResult = $client->getStatus($entity->cod_request);
            // Solo incrementar poll_attempts si la consulta fue exitosa
            $state->poll_attempts++;
            $state->last_polled_at = now();
        } catch (TransientHttpException $e) {
            // Error de red o 5xx — reprogramar sin incrementar intentos de consulta
            $logger->warning('viafirma.poll.transient_error', [
                'id'      => $entity->id,
                'message' => $e->getMessage(),
            ]);
            $delay = $scheduler->retryAfter($entity);
            $state->next_poll_at = now()->addSeconds($delay);
            $state->save();
            self::dispatch($this->requestId)->delay(now()->addSeconds($delay));
            return;
        }

        // ── FSM: actualizar estado ────────────────────────────────────────
        $fsm->transition($entity, $statusResult->status, $statusResult->raw);

        // ── Recargar state después de la transición FSM ──────────────────────
        // La FSM mutó $entity->state, pero nuestra variable local $state es stale
        $state = $entity->state;

        // ── Decidir próximo paso ──────────────────────────────────────────
        if ($entity->isReadyToDownload()) {
            // Validar que remote_status indica P7B disponible
            if (!in_array($state->remote_status, ['Generated_Not_Downloaded', 'Generated_And_Downloaded', 'signedContract'])) {
                $logger->warning('viafirma.poll.ready_but_no_p7b', [
                    'id'            => $entity->id,
                    'remote_status' => $state->remote_status,
                ]);
                // No está realmente listo — continuar polling
            } else {
                $state->next_poll_at = null;
                $state->save();
                $logger->info('viafirma.poll.ready_to_download', [
                    'id'       => $entity->id,
                    'publicId' => $entity->public_id,
                    'remote'   => $statusResult->status->value,
                ]);
                DownloadP7bJob::dispatch($entity->id)->delay(now()->addSeconds(5));
                return;
            }
        }

        // Estados terminales — detener polling
        if ($entity->isTerminal()) {
            $state->next_poll_at = null;
            $state->save();
            $logger->info('viafirma.poll.stopped', [
                'id'     => $entity->id,
                'state'  => $state->internal_state->value,
                'remote' => $statusResult->status->value,
            ]);
            return;
        }

        // Errores irrecuperables (FAILED, no FAILED_RECOVERABLE) — detener polling
        if ($entity->isFailed() && !$state->internal_state->isRecoverable()) {
            $state->next_poll_at = null;
            $state->save();
            $logger->info('viafirma.poll.stopped_failed', [
                'id'          => $entity->id,
                'state'       => $state->internal_state->value,
                'error_code'  => $state->last_error_code,
                'remote'      => $statusResult->status->value,
            ]);
            return;
        }

        // Errores recuperables (FAILED_RECOVERABLE) — continuar polling en intervalos más largos
        if ($state->internal_state->isRecoverable()) {
            $delay = $scheduler->recoveryDelay($entity);
            $state->next_poll_at = now()->addSeconds($delay);
            $state->save();

            self::dispatch($this->requestId)->delay(now()->addSeconds($delay));

            $logger->info('viafirma.poll.recoverable_error_continue', [
                'id'              => $entity->id,
                'error_code'      => $state->last_error_code,
                'delay_s'         => $delay,
                'attempts'        => $state->poll_attempts,
                'human_awaiting'  => 'esperando intervención del operador en Viafirma',
            ]);
            return;
        }

        // Sigue en curso → reprogramar en ~60 s
        $delay = $scheduler->nextDelay($entity);
        $state->next_poll_at = now()->addSeconds($delay);
        $state->save();

        self::dispatch($this->requestId)->delay(now()->addSeconds($delay));

        $logger->info('viafirma.poll.rescheduled', [
            'id'       => $entity->id,
            'remote'   => $statusResult->status->value,
            'delay_s'  => $delay,
            'attempts' => $state->poll_attempts,
        ]);
    }
}
