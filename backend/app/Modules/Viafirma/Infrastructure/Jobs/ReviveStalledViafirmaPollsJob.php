<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;

/**
 * Watchdog de seguridad: revive solicitudes Viafirma huérfanas.
 *
 * Se ejecuta cada 5 minutos vía Kernel scheduler.
 *
 * Una solicitud se considera "huérfana" si:
 *  - Está en estado SUBMITTED o POLLING (no terminal)
 *  - Tiene cod_request (ya fue enviada a Viafirma)
 *  - next_poll_at es NULL o lleva más de 20 min sin actualizarse
 *    (indica que el PollViafirmaStatusJob no se reprogramó correctamente)
 *
 * Para cada huérfana: despacha PollViafirmaStatusJob con delay aleatorio
 * (5-30s) para escalonar los polls y evitar ráfagas simultáneas.
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

    public function handle(LoggerInterface $logger): void
    {
        try {
            $stalledThreshold = now()->subMinutes(20);

            $pollingStates = [
                InternalState::SUBMITTED->value,
                InternalState::POLLING->value,
            ];

            $baseQuery = ViafirmaCertificateRequest::query()
                ->whereIn('internal_state', $pollingStates)
                ->whereNotNull('cod_request')
                ->where(function ($q) use ($stalledThreshold) {
                    $q->whereNull('next_poll_at')
                        ->orWhere('next_poll_at', '<', $stalledThreshold);
                });

            // Guard rápido: si no hay ningún registro huérfano, salir sin coste
            if (!$baseQuery->exists()) {
                $logger->info('viafirma.watchdog.no_stalled');
                return;
            }

            $stalled = $baseQuery->get(['id', 'internal_state', 'next_poll_at', 'poll_attempts']);

            $logger->warning('viafirma.watchdog.reviving', ['count' => $stalled->count()]);

            foreach ($stalled as $entity) {
                $delay = random_int(5, 30);

                PollViafirmaStatusJob::dispatch($entity->id)
                    ->delay(now()->addSeconds($delay));

                $entity->update(['next_poll_at' => now()->addSeconds($delay)]);

                $logger->info('viafirma.watchdog.revived', [
                    'id'       => $entity->id,
                    'state'    => $entity->internal_state instanceof InternalState
                        ? $entity->internal_state->value
                        : $entity->internal_state,
                    'attempts' => $entity->poll_attempts,
                    'delay_s'  => $delay,
                ]);
            }
        } catch (\Throwable $e) {
            // Registrar el error pero NO relanzar — el job debe terminar con "done"
            // incluso si la tabla no existe, la BD no responde o hay un error inesperado.
            $logger->error('viafirma.watchdog.error', [
                'message' => $e->getMessage(),
                'class'   => get_class($e),
            ]);
        }
    }
}
