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
 * Watchdog que busca solicitudes huérfanas y las re-arma (V-305).
 *
 * Ejecutar cada 15 min vía Kernel scheduler.
 *
 * Criterio de "huérfana":
 *  - internal_state NO terminal (COMPLETED, FAILED, EXPIRED)
 *  - internal_state = POLLING o SUBMITTED
 *  - next_poll_at < now() - 20 minutos (debería haber sido procesada)
 *  - O next_poll_at IS NULL (nunca se programó)
 */
final class ReviveStalledViafirmaPollsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 60;

    /**
     * Tags para Telescope.
     *
     * @return string[]
     */
    public function tags(): array
    {
        return ['viafirma:watchdog'];
    }

    public function handle(LoggerInterface $logger): void
    {
        $stalledThreshold = now()->subMinutes(20);

        $terminalStates = [
            InternalState::COMPLETED->value,
            InternalState::FAILED->value,
            InternalState::EXPIRED->value,
        ];

        $pollingStates = [
            InternalState::SUBMITTED->value,
            InternalState::POLLING->value,
        ];

        $stalled = ViafirmaCertificateRequest::query()
            ->whereIn('internal_state', $pollingStates)
            ->whereNotIn('internal_state', $terminalStates)
            ->whereNotNull('cod_request')
            ->where(function ($q) use ($stalledThreshold) {
                $q->where('next_poll_at', '<', $stalledThreshold)
                    ->orWhereNull('next_poll_at');
            })
            ->get(['id', 'internal_state', 'next_poll_at', 'poll_attempts']);

        if ($stalled->isEmpty()) {
            $logger->info('viafirma.watchdog.no_stalled');
            return;
        }

        $logger->warning('viafirma.watchdog.reviving', ['count' => $stalled->count()]);

        foreach ($stalled as $entity) {
            PollViafirmaStatusJob::dispatch($entity->id)
                ->delay(now()->addSeconds(random_int(5, 30)));

            $entity->update(['next_poll_at' => now()->addSeconds(30)]);

            $logger->info('viafirma.watchdog.revived', [
                'id'       => $entity->id,
                'state'    => $entity->internal_state->value ?? $entity->internal_state,
                'attempts' => $entity->poll_attempts,
            ]);
        }
    }
}
