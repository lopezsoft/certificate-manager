<?php

declare(strict_types=1);

namespace App\Jobs\Certificate;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Watchdog que busca solicitudes de certificados estancadas en estado PROCESSING
 * que nunca llegaron al proveedor (Viafirma) y las reintenta automáticamente.
 *
 * Se ejecuta periódicamente vía Kernel para asegurar resiliencia en la emisión.
 */
class RetryStalledIssuancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 60;

    public function handle(): void
    {
        try {
            // 3 minutos de gracia — cubre los 3 reintentos del AutoIssueViafirmaJob
            // (backoff=30s c/u, ~90s total) antes de declarar la emisión estancada.
            $stalledThreshold = now()->subMinutes(3);

            $baseQuery = CertificateRequest::query()
                ->select('certificate_requests.*')
                ->join('companies', 'companies.id', '=', 'certificate_requests.company_id')
                ->where('certificate_requests.request_status', CertificateRequestStatusEnum::PROCESSING->value)
                ->where('certificate_requests.created_at', '<', $stalledThreshold)
                // Filtro explícito: solo solicitudes cuya empresa usa Viafirma como proveedor.
                // Evita contaminar solicitudes de otros proveedores (email, etc.) con este watchdog.
                ->where('companies.issuance_provider', '=', 'viafirma')
                ->whereNotNull('certificate_requests.legal_rep_email')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('viafirma_certificate_requests')
                          ->whereColumn('viafirma_certificate_requests.certificate_request_id', 'certificate_requests.id');
                });

            // Guard rápido: si no hay ninguna solicitud estancada, salir sin coste
            if (!$baseQuery->exists()) {
                Log::info('certificate.watchdog.no_stalled_issuances');
                return;
            }

            $stalledRequests = $baseQuery->get();

            Log::warning('certificate.watchdog.retrying_issuances', ['count' => $stalledRequests->count()]);

            foreach ($stalledRequests as $cr) {
                AutoIssueViafirmaJob::dispatch($cr->id)
                    ->delay(now()->addSeconds(random_int(5, 30)));

                Log::info('certificate.watchdog.retried', ['cr_id' => $cr->id]);
            }
        } catch (\Throwable $e) {
            // NO relanzar — el job debe terminar con "done" aunque haya error de BD
            Log::error('certificate.watchdog.error', [
                'message' => $e->getMessage(),
                'class'   => get_class($e),
            ]);
        }
    }
}
