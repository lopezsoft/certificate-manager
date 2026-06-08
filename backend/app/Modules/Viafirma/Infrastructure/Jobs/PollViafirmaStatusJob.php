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
use Psr\Log\LoggerInterface;

/**
 * Polling de estado Viafirma — se auto-reprograma cada ~60 s hasta resolverse.
 *
 * Flujo:
 *   1. GET /request/{codRequest}/status
 *   2. FSM actualiza internal_state + remote_status
 *   3. Si estado = Generated_Not_Downloaded → despacha DownloadP7bJob (descarga)
 *   4. Si estado terminal o fallo → detiene polling
 *   5. Si sigue en curso → reprograma a los 60 s
 *
 * ShouldBeUnique evita polls concurrentes sobre la misma solicitud.
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
        LoggerInterface $logger,
    ): void {
        $entity = ViafirmaCertificateRequest::find($this->requestId);

        if ($entity === null) {
            $logger->warning('viafirma.poll.entity_not_found', ['id' => $this->requestId]);
            return;
        }

        // Guard: estado terminal → no hacer nada más
        if ($entity->isTerminal()) {
            $logger->info('viafirma.poll.skip_terminal', [
                'id'    => $entity->id,
                'state' => $entity->internal_state->value,
            ]);
            return;
        }

        // Guard: SLA o máximo de intentos superado → expirar
        if ($entity->hasExpired() || $scheduler->hasExceededSla($entity) || $scheduler->hasExceededMaxAttempts($entity)) {
            $fsm->markExpired($entity);
            $entity->save();
            $logger->warning('viafirma.poll.expired', [
                'id'       => $entity->id,
                'attempts' => $entity->poll_attempts,
            ]);
            return;
        }

        // Guard: sin codRequest → no se puede consultar
        if (empty($entity->cod_request)) {
            $logger->warning('viafirma.poll.no_cod_request', ['id' => $entity->id]);
            return;
        }

        // ── Consultar estado en Viafirma ──────────────────────────────────
        $entity->poll_attempts++;
        $entity->last_polled_at = now();

        try {
            // GET /request/{codRequest}/status — responde {"code": "<estado>"}
            $statusResult = $client->getStatus($entity->cod_request);
        } catch (TransientHttpException $e) {
            // Error de red o 5xx — reprogramar y continuar
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

        // ── FSM: actualizar estado ────────────────────────────────────────
        $fsm->transition($entity, $statusResult->status, $statusResult->raw);

        // ── Decidir próximo paso ──────────────────────────────────────────
        if ($entity->isReadyToDownload()) {
            // Estado Generated_Not_Downloaded → despachar descarga del P7B
            $entity->next_poll_at = null;
            $entity->save();
            $logger->info('viafirma.poll.ready_to_download', [
                'id'        => $entity->id,
                'publicId'  => $entity->public_id,
                'remote'    => $statusResult->status->value,
            ]);
            // GET /downloadCertificateServlet?req={publicId}
            DownloadP7bJob::dispatch($entity->id)->delay(now()->addSeconds(5));
            return;
        }

        if ($entity->isTerminal() || $entity->isFailed()) {
            $entity->next_poll_at = null;
            $entity->save();
            $logger->info('viafirma.poll.stopped', [
                'id'     => $entity->id,
                'state'  => $entity->internal_state->value,
                'remote' => $statusResult->status->value,
            ]);
            return;
        }

        // Sigue en curso → reprogramar en ~60 s
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
