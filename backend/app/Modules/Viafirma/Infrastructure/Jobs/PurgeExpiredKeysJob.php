<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
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
        SafePemLogger $logger,
    ): void {
        // Criterios de purga:
        // 1. Certificados ya vencidos (expiration_date < now())
        // 2. Certificados revocados o marcados como DELETE (request_status = 'DELETE')

        $candidates = ViafirmaCertificateRequestState::query()
            ->where('internal_state', InternalState::COMPLETED->value)
            ->with('viafirmaCertificateRequest.certificateRequest')
            ->whereHas('viafirmaCertificateRequest.certificateRequest', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('expiration_date', '<', now())
                       ->orWhere('request_status', 'DELETE');
                });
            })
            ->get(['id', 'viafirma_certificate_request_id', 'internal_state', 'p12_storage_path', 'p7b_storage_path', 'key_vault_ref']);

        if ($candidates->isEmpty()) {
            $logger->info('viafirma.purge.no_candidates');
            return;
        }

        $logger->info('viafirma.purge.start', ['count' => $candidates->count()]);
        $purged = 0;

        foreach ($candidates as $stateRecord) {
            try {
                // Disco genérico de certificados (las rutas exactas vienen de BD).
                $disk = app(\App\Services\Certificates\CertificateStoragePathResolver::class)->disk();

                // Eliminar archivo P12 físico (ZIP comprimido)
                if ($stateRecord->p12_storage_path) {
                    if (Storage::disk($disk)->exists($stateRecord->p12_storage_path)) {
                        Storage::disk($disk)->delete($stateRecord->p12_storage_path);
                    }
                    // Marcar en file_managers como DELETED
                    \App\Models\FileManager::where('file_path', $stateRecord->p12_storage_path)
                        ->update(['status' => 'DELETED']);
                    $stateRecord->p12_storage_path = null;
                }

                // Eliminar archivo P7B físico
                if ($stateRecord->p7b_storage_path) {
                    if (Storage::disk($disk)->exists($stateRecord->p7b_storage_path)) {
                        Storage::disk($disk)->delete($stateRecord->p7b_storage_path);
                    }
                    // Marcar en file_managers como DELETED
                    \App\Models\FileManager::where('file_path', $stateRecord->p7b_storage_path)
                        ->update(['status' => 'DELETED']);
                    $stateRecord->p7b_storage_path = null;
                }

                // Mantener referencia de llave privada (NO eliminar)
                // Las llaves se guardan en KeyVault y no se purgan en el nuevo modelo de negocio

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
