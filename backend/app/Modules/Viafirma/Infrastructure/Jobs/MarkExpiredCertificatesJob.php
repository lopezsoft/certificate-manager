<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Marcar como EXPIRED los certificados cuya vida útil comercial o técnica finalizó
 * y no fueron revocados.
 *
 * Se debe programar diariamente.
 */
final class MarkExpiredCertificatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function handle(): void
    {
        $cutoffDate = now();

        $candidates = CertificateRequest::query()
            ->where('request_status', CertificateRequestStatusEnum::PROCESSED->value)
            ->where('expiration_date', '<', $cutoffDate)
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $expiredCount = 0;

        foreach ($candidates as $cr) {
            try {
                $cr->update(['request_status' => CertificateRequestStatusEnum::EXPIRED->value]);

                // Registrar en change_histories
                ChangeHistory::create([
                    'certificate_request_id' => $cr->id,
                    'user_id'                => null,
                    'user_of_change'         => 'SYSTEM (Auto Expiración)',
                    'status'                 => CertificateRequestStatusEnum::EXPIRED->value,
                    'comments'               => 'El certificado ha superado su fecha de expiración y ya no es válido comercial o técnicamente.',
                ]);

                $expiredCount++;
            } catch (\Throwable $e) {
                Log::error('viafirma.mark_expired.failed', [
                    'cr_id' => $cr->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('viafirma.mark_expired.complete', ['expired' => $expiredCount]);
    }
}
