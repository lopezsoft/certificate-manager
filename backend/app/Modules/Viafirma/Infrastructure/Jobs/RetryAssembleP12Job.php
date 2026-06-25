<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\UseCases\RedownloadCertificateUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Throwable;


/**
 * Job dedicado para reintentar la descarga y ensamblado de P12 para certificados FAILED_RECOVERABLE.
 *
 * Reemplaza el closure anónimo en AutoRedownloadPendingViafirmaJob para:
 * - Registrar errores en failed_jobs con nombre identificable
 * - Tener control sobre reintentos y timeout
 * - Proporcionar logging detallado de fallos
 *
 * Se despacha desde AutoRedownloadPendingViafirmaJob cuando detecta candidatos para re-descarga.
 */
final class RetryAssembleP12Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(
        public readonly int $certificateRequestId,
    ) {}

    /** @return string[] */
    public function tags(): array
    {
        return ['viafirma:retry-assemble', "viafirma:retry-assemble:{$this->certificateRequestId}"];
    }

    public function handle(
        RedownloadCertificateUseCase $useCase,
        SafePemLogger $logger,
    ): void {
        try {
            $logger->info('viafirma.retry_assemble.start', [
                'certificate_request_id' => $this->certificateRequestId,
                'attempt'                => $this->attempts(),
            ]);

            // Llamar al use case para re-descargar y ensamblar
            $result = $useCase->handle(
                certificateRequestId: $this->certificateRequestId,
                adminUserId:          null,
            );

            $logger->info('viafirma.retry_assemble.success', [
                'certificate_request_id' => $this->certificateRequestId,
                'viafirma_id'            => $result->viafirmaId,
                'internal_state'         => $result->internalState,
                'remote_status'          => $result->remoteStatus,
            ]);

        } catch (Throwable $e) {
            $logger->error('viafirma.retry_assemble.failed', [
                'certificate_request_id' => $this->certificateRequestId,
                'attempt'                => $this->attempts(),
                'error'                  => $e->getMessage(),
                'class'                  => get_class($e),
            ]);

            // Relanzar para que Laravel registre en failed_jobs
            throw $e;
        }
    }
}
