<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Models\CertificateRequest;
use App\Modules\Viafirma\Application\UseCases\RedownloadCertificateUseCase;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * FixIncompleteViafirmaCertificatesJob — Detecta y corrige certificados PROCESSED
 * que tienen datos incompletos (PIN o revocation_request_code faltantes).
 *
 * Se ejecuta cada 3 minutos vía cron para corregir registros que no fueron
 * completados correctamente en el flujo normal.
 *
 * Criterios de detección:
 *  - certificate_requests.request_status = PROCESSED
 *  - certificate_requests.pin IS NULL
 *    O viafirma_certificate_request_states.revocation_request_code IS NULL
 *  - key_vault_ref NO está purgada
 *
 * Acción: Re-descarga el P7B y regenera el P12 con PIN y código de revocación.
 */
final class FixIncompleteViafirmaCertificatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    /** @return string[] */
    public function tags(): array
    {
        return ['viafirma:fix-incomplete'];
    }

    public function __construct()
    {
        $this->onQueue('redownload');
    }

    public function handle(
        RedownloadCertificateUseCase $useCase,
        SafePemLogger $logger,
    ): void {
        $logger->info('viafirma.fix_incomplete.start');

        try {
            // Detectar certificados PROCESSED con datos incompletos (solo para proveedor Viafirma)
            $incompleteRequests = CertificateRequest::query()
                ->join('companies', 'certificate_requests.company_id', '=', 'companies.id')
                ->join('viafirma_certificate_requests', 'certificate_requests.id', '=', 'viafirma_certificate_requests.certificate_request_id')
                ->join('viafirma_certificate_request_states', 'viafirma_certificate_requests.id', '=', 'viafirma_certificate_request_states.viafirma_certificate_request_id')
                ->where('certificate_requests.request_status', 'PROCESSED')
                ->where('companies.issuance_provider', 'viafirma')
                ->where(function ($query) {
                    $query->whereNull('certificate_requests.pin')
                          ->orWhereNull('viafirma_certificate_request_states.revocation_request_code');
                })
                ->where('viafirma_certificate_request_states.key_vault_ref', '!=', 'PURGED')
                ->whereNotNull('viafirma_certificate_request_states.key_vault_ref')
                ->select('certificate_requests.id', 'certificate_requests.uuid')
                ->distinct()
                ->get();

            $totalCount = $incompleteRequests->count();
            $logger->info('viafirma.fix_incomplete.found', ['count' => $totalCount]);

            if ($totalCount === 0) {
                $logger->info('viafirma.fix_incomplete.no_records_found');
                return;
            }

            $successCount = 0;
            $failureCount = 0;

            foreach ($incompleteRequests as $request) {
                try {
                    $logger->info('viafirma.fix_incomplete.processing', [
                        'certificate_request_id' => $request->id,
                        'uuid'                   => $request->uuid,
                    ]);

                    // Usar RedownloadCertificateUseCase para regenerar el P12
                    // adminUserId = null indica que es una corrección automática del sistema
                    $result = $useCase->handle(
                        certificateRequestId: $request->id,
                        adminUserId:          null,
                    );

                    $logger->info('viafirma.fix_incomplete.success', [
                        'certificate_request_id' => $request->id,
                        'viafirma_id'            => $result->viafirmaId,
                        'internal_state'         => $result->internalState,
                    ]);

                    $successCount++;

                } catch (Throwable $e) {
                    $failureCount++;
                    $logger->error('viafirma.fix_incomplete.failed', [
                        'certificate_request_id' => $request->id,
                        'error'                  => $e->getMessage(),
                        'class'                  => get_class($e),
                    ]);
                }
            }

            $logger->info('viafirma.fix_incomplete.completed', [
                'total'    => $totalCount,
                'success'  => $successCount,
                'failures' => $failureCount,
            ]);

        } catch (Throwable $e) {
            $logger->error('viafirma.fix_incomplete.fatal_error', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            throw $e;
        }
    }
}
