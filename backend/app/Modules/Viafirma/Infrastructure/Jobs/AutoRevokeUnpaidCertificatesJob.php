<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Modules\Viafirma\Application\DTOs\RevokeInputDto;
use App\Modules\Viafirma\Application\UseCases\RevokeCertificateUseCase;
use App\Modules\Viafirma\Domain\Enums\RevocationReason;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Revocación Comercial Automática (Fase 3).
 *
 * Busca certificados con vigencia de 1 año (life=1) que han superado su fecha de expiración
 * más un periodo de gracia configurable, y los revoca automáticamente alegando "Cese de Operaciones".
 *
 * Se debe programar diariamente (ej. 03:00 AM).
 */
final class AutoRevokeUnpaidCertificatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function handle(RevokeCertificateUseCase $revokeUseCase): void
    {
        $graceDays = (int) config('viafirma.revocation.grace_days', 15);
        $cutoffDate = now()->subDays($graceDays);

        // Buscar solicitudes PROCESSED con life=1 cuya fecha de expiración haya pasado el límite.
        $candidates = CertificateRequest::query()
            ->where('request_status', CertificateRequestStatusEnum::PROCESSED->value)
            ->where('life', 1)
            ->where('expiration_date', '<', $cutoffDate)
            ->with('viafirmaCertificateRequest')
            ->get();

        if ($candidates->isEmpty()) {
            Log::info('viafirma.auto_revoke.no_candidates');
            return;
        }

        Log::info('viafirma.auto_revoke.start', ['count' => $candidates->count(), 'grace_days' => $graceDays]);

        $revokedCount = 0;

        foreach ($candidates as $cr) {
            $viafirmaEntity = $cr->viafirmaCertificateRequest;
            
            if (!$viafirmaEntity) {
                continue;
            }

            $revocationCode = $viafirmaEntity->state?->revocation_request_code;

            if (empty($revocationCode)) {
                Log::warning('viafirma.auto_revoke.missing_revocation_code', ['cr_id' => $cr->id]);
                continue;
            }

            try {
                $dto = new RevokeInputDto(
                    viafirmaCertificateRequestId: $viafirmaEntity->id,
                    revokingCode:                 $revocationCode,
                    revocationReason:             RevocationReason::CESSATION_OF_OPERATION,
                    revokedByUserId:              null
                );

                $revokeUseCase->handle($dto);
                $revokedCount++;

            } catch (\Throwable $e) {
                Log::error('viafirma.auto_revoke.failed', [
                    'cr_id' => $cr->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('viafirma.auto_revoke.complete', ['revoked' => $revokedCount, 'total' => $candidates->count()]);
    }
}
