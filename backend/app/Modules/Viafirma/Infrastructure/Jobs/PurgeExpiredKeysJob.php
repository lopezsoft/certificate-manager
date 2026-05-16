<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;

/**
 * Purga segura de llaves privadas de solicitudes COMPLETED/FAILED (V-410).
 *
 * Criterio:
 *  - Estado terminal (COMPLETED, FAILED, EXPIRED)
 *  - Ensamblado hace más de 72h (o fallido hace más de 72h)
 *  - key_vault_ref no vacío
 *
 * La purga destruye la referencia del KeyVault (el material cifrado se elimina)
 * y marca la referencia como purgada.
 *
 * Ejecutar diariamente vía Kernel scheduler.
 */
final class PurgeExpiredKeysJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 120;

    /** @return string[] */
    public function tags(): array
    {
        return ['viafirma:purge-keys'];
    }

    public function handle(
        KeyVault $vault,
        LoggerInterface $logger,
    ): void {
        $retentionHours = 72;
        $cutoff = now()->subHours($retentionHours);

        $candidates = ViafirmaCertificateRequest::query()
            ->whereIn('internal_state', [
                InternalState::COMPLETED->value,
                InternalState::FAILED->value,
                InternalState::EXPIRED->value,
            ])
            ->whereNotNull('key_vault_ref')
            ->where('key_vault_ref', '!=', 'PURGED')
            ->where(function ($q) use ($cutoff) {
                $q->where('assembled_at', '<', $cutoff)
                    ->orWhere('updated_at', '<', $cutoff);
            })
            ->get(['id', 'key_vault_ref', 'p12_password_ref', 'internal_state']);

        if ($candidates->isEmpty()) {
            $logger->info('viafirma.purge.no_candidates');
            return;
        }

        $logger->info('viafirma.purge.start', ['count' => $candidates->count()]);
        $purged = 0;

        foreach ($candidates as $entity) {
            try {
                // Purgar llave privada
                if ($entity->key_vault_ref && $entity->key_vault_ref !== 'PURGED') {
                    if ($vault->exists($entity->key_vault_ref)) {
                        $vault->destroy($entity->key_vault_ref);
                    }
                    $entity->key_vault_ref = 'PURGED';
                }

                // Purgar PIN del P12 (si aplica)
                if ($entity->p12_password_ref && $entity->p12_password_ref !== 'PURGED') {
                    if ($vault->exists($entity->p12_password_ref)) {
                        $vault->destroy($entity->p12_password_ref);
                    }
                    $entity->p12_password_ref = 'PURGED';
                }

                $entity->save();
                $purged++;

                $logger->info('viafirma.purge.success', ['id' => $entity->id]);

            } catch (\Throwable $e) {
                $logger->error('viafirma.purge.failed', [
                    'id'    => $entity->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $logger->info('viafirma.purge.complete', ['purged' => $purged, 'total' => $candidates->count()]);
    }
}
