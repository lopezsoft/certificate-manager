<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

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
 *
 * NOTA: Tras la normalización, los campos de estado se encuentran en
 * viafirma_certificate_request_states.
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
        SafePemLogger $logger,
    ): void {
        $retentionHours = 72;
        $cutoff = now()->subHours($retentionHours);

        // Consultar directamente la tabla de estados (normalizada)
        $candidates = ViafirmaCertificateRequestState::query()
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
            ->get(['id', 'viafirma_certificate_request_id', 'key_vault_ref', 'p12_password_ref', 'internal_state']);

        if ($candidates->isEmpty()) {
            $logger->info('viafirma.purge.no_candidates');
            return;
        }

        $logger->info('viafirma.purge.start', ['count' => $candidates->count()]);
        $purged = 0;

        foreach ($candidates as $stateRecord) {
            try {
                // Purgar llave privada
                if ($stateRecord->key_vault_ref && $stateRecord->key_vault_ref !== 'PURGED') {
                    if ($vault->exists($stateRecord->key_vault_ref)) {
                        $vault->destroy($stateRecord->key_vault_ref);
                    }
                    $stateRecord->key_vault_ref = 'PURGED';
                }

                // Purgar PIN del P12 (si aplica)
                if ($stateRecord->p12_password_ref && $stateRecord->p12_password_ref !== 'PURGED') {
                    if ($vault->exists($stateRecord->p12_password_ref)) {
                        $vault->destroy($stateRecord->p12_password_ref);
                    }
                    $stateRecord->p12_password_ref = 'PURGED';
                }

                $stateRecord->save();
                $purged++;

                $logger->info('viafirma.purge.success', [
                    'viafirma_id' => $stateRecord->viafirma_certificate_request_id,
                ]);

            } catch (\Throwable $e) {
                $logger->error('viafirma.purge.failed', [
                    'viafirma_id' => $stateRecord->viafirma_certificate_request_id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $logger->info('viafirma.purge.complete', ['purged' => $purged, 'total' => $candidates->count()]);
    }
}
