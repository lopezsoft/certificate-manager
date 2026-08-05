<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Watchdog de seguridad: revive solicitudes Viafirma huérfanas.
 *
 * Se ejecuta cada 5 minutos vía Kernel scheduler.
 *
 * Una solicitud se considera "huérfana" si:
 *  - Está en estado SUBMITTED, POLLING, o FAILED_RECOVERABLE (esperando
 *    intervención del operador, pero que debe seguir siendo consultada)
 *  - Tiene cod_request (ya fue enviada a Viafirma)
 *  - next_poll_at es NULL o lleva más de 20 min sin actualizarse
 *    (indica que el PollViafirmaStatusJob no se reprogramó correctamente,
 *    o que quedó con next_poll_at=null por versiones previas del job que
 *    detenían el polling en cualquier estado failure-like)
 *
 * Para cada huérfana: despacha PollViafirmaStatusJob con delay aleatorio
 * (5-30s) para escalonar los polls y evitar ráfagas simultáneas.
 *
 * NOTA: Tras la normalización, los campos de estado se encuentran en
 * viafirma_certificate_request_states. El scope orphanedPolling() está
 * definido en ViafirmaCertificateRequestState.
 */
final class ReviveStalledViafirmaPollsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 3;
    public int $backoff = 30;
    public int $timeout = 60;

    /** @return string[] */
    public function tags(): array
    {
        return ['viafirma:watchdog'];
    }

    public function handle(SafePemLogger $logger): void
    {
        try {
            // Usar el scope del modelo de estado para encontrar huérfanas
            $baseQuery = ViafirmaCertificateRequestState::orphanedPolling(20)
                ->whereHas('viafirmaCertificateRequest', function ($q) {
                    $q->whereNotNull('cod_request');
                });

            // Guard rápido: si no hay ningún registro huérfano, salir sin coste
            if (!$baseQuery->exists()) {
                $logger->info('viafirma.watchdog.no_stalled');
            } else {

                $stalled = $baseQuery->get(['id', 'viafirma_certificate_request_id', 'internal_state', 'next_poll_at', 'poll_attempts']);

                $logger->warning('viafirma.watchdog.reviving', ['count' => $stalled->count()]);

                foreach ($stalled as $stateRecord) {
                    $delay = random_int(5, 30);

                    PollViafirmaStatusJob::dispatch($stateRecord->viafirma_certificate_request_id)
                        ->delay(now()->addSeconds($delay));

                    $stateRecord->update(['next_poll_at' => now()->addSeconds($delay)]);

                    $logger->info('viafirma.watchdog.revived', [
                        'viafirma_id' => $stateRecord->viafirma_certificate_request_id,
                        'state'       => $stateRecord->internal_state instanceof \App\Modules\Viafirma\Domain\Enums\InternalState
                            ? $stateRecord->internal_state->value
                            : $stateRecord->internal_state,
                        'attempts'    => $stateRecord->poll_attempts,
                        'delay_s'     => $delay,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $logger->error('viafirma.watchdog.error', [
                'message' => $e->getMessage(),
                'class'   => get_class($e),
            ]);
        }
    }
}
