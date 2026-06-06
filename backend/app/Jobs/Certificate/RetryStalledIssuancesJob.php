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

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(): void
    {
        // 10 minutos de gracia para dar tiempo a que los retries nativos
        // de Laravel del job original terminen o fallen.
        $stalledThreshold = now()->subMinutes(10);

        // Buscar solicitudes PROCESSING que llevan más de 10 minutos y no tienen registro en Viafirma
        // IMPORTANTE: Solo para empresas cuyo proveedor es Viafirma (las de mail se quedan en PROCESSING por diseño).
        $stalledRequests = CertificateRequest::query()
            ->select('certificate_requests.*')
            ->join('companies', 'companies.id', '=', 'certificate_requests.company_id')
            ->where('certificate_requests.request_status', CertificateRequestStatusEnum::PROCESSING->value)
            ->where('certificate_requests.created_at', '<', $stalledThreshold)
            ->where('companies.issuance_provider', 'viafirma')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('viafirma_certificate_requests')
                      ->whereColumn('viafirma_certificate_requests.certificate_request_id', 'certificate_requests.id');
            })
            ->get();

        if ($stalledRequests->isEmpty()) {
            Log::info('certificate.watchdog.no_stalled_issuances');
            return;
        }

        Log::warning('certificate.watchdog.retrying_issuances', ['count' => $stalledRequests->count()]);

        foreach ($stalledRequests as $cr) {
            // Se despacha el job de emisión nuevamente para reintentarlo
            AutoIssueViafirmaJob::dispatch($cr->id)
                ->delay(now()->addSeconds(random_int(5, 30)));
            
            Log::info('certificate.watchdog.retried', [
                'cr_id' => $cr->id,
            ]);
        }
    }
}
