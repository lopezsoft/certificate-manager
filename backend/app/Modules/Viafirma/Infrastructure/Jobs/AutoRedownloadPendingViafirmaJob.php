<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\UseCases\RedownloadCertificateUseCase;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Throwable;

/**
 * Watchdog automático que detecta certificados Viafirma en estado FAILED_RECOVERABLE
 * cuyo estado remoto ya es Generated_And_Downloaded, y los re-descarga automáticamente.
 *
 * Escenario que resuelve:
 *   AssembleP12Job descarga el P7B y ensambla el P12 en Viafirma (estado remoto:
 *   Generated_And_Downloaded), pero un error transitorio (timeout de red, fallo de S3,
 *   excepción en el vault) ocurre DESPUÉS de la descarga pero ANTES de actualizar la BD.
 *   El job falla y el estado interno queda en FAILED_RECOVERABLE, aunque el certificado
 *   ya existe en Viafirma. Este watchdog detecta y corrige esa inconsistencia.
 *
 * Ejecutado por el scheduler cada 5 minutos (ver Kernel.php — Job 9).
 *
 * Límite de reintentos automáticos: 5 (columna auto_redownload_attempts en
 * viafirma_certificate_request_states).
 * Superado ese límite, el candidato queda excluido del scope y requiere
 * intervención manual del ADMIN vía POST /api/v1/certificate-request/{id}/issuance/redownload.
 *
 * NOTA: Tras la normalización, los campos de estado se encuentran en
 * viafirma_certificate_request_states. El scope pendingAutoRedownload() está
 * definido en ViafirmaCertificateRequestState.
 */
final class AutoRedownloadPendingViafirmaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * El watchdog en sí solo se intenta una vez.
     * Los reintentos individuales se controlan con auto_redownload_attempts.
     */
    public int $tries = 1;

    /** Timeout suficiente para consultar BD + despachar closures. */
    public int $timeout = 60;

    /** @return string[] */
    public function tags(): array
    {
        return ['viafirma:watchdog', 'viafirma:auto-redownload'];
    }

    public function handle(RedownloadCertificateUseCase $useCase, SafePemLogger $logger): void
    {
        try {
            // Consultar candidatos desde la tabla de estados (normalizada)
            $candidates = ViafirmaCertificateRequestState::pendingAutoRedownload()
                ->with('viafirmaCertificateRequest')
                ->get();

            if ($candidates->isEmpty()) {
                $logger->info('viafirma.auto_redownload.no_candidates');
                return;
            }

            $logger->info('viafirma.auto_redownload.candidates_found', [
                'count' => $candidates->count(),
            ]);

            $dispatched = 0;

            foreach ($candidates as $stateRecord) {
                try {
                    // Incrementar contador ANTES de despachar para que el scope
                    // no vuelva a seleccionar este registro en el mismo ciclo.
                    $stateRecord->increment('auto_redownload_attempts');

                    $crId    = $stateRecord->viafirmaCertificateRequest?->certificate_request_id;
                    $attempt = (int) $stateRecord->auto_redownload_attempts;

                    if ($crId === null) {
                        $logger->warning('viafirma.auto_redownload.no_cr_id', [
                            'state_id' => $stateRecord->id,
                        ]);
                        continue;
                    }

                    // Despachar con delay aleatorio (5-30s) para evitar thundering herd
                    dispatch(static function () use ($useCase, $crId): void {
                        // adminUserId = null → invocación desde sistema (no hay usuario admin)
                        $useCase->handle(
                            certificateRequestId: $crId,
                            adminUserId:          null,
                        );
                    })->delay(now()->addSeconds(random_int(5, 30)));

                    $dispatched++;

                    $logger->info('viafirma.auto_redownload.dispatched', [
                        'viafirma_id' => $stateRecord->viafirma_certificate_request_id,
                        'cr_id'       => $crId,
                        'attempt'     => $attempt,
                    ]);

                } catch (Throwable $e) {
                    // NO relanzar — continuar con el siguiente candidato.
                    $logger->error('viafirma.auto_redownload.dispatch_error', [
                        'state_id' => $stateRecord->id,
                        'error'    => $e->getMessage(),
                        'class'    => get_class($e),
                    ]);
                }
            }

            $logger->info('viafirma.auto_redownload.completed', [
                'total_candidates' => $candidates->count(),
                'dispatched'       => $dispatched,
                'skipped'          => $candidates->count() - $dispatched,
            ]);

        } catch (Throwable $e) {
            $logger->error('viafirma.auto_redownload.watchdog_error', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
        }
    }
}
