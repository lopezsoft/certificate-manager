<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\Services\KycImmediateWebhookBatcher;
use App\Modules\Viafirma\Domain\Contracts\KycWebhookNotifierContract;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Se ejecuta tras la ventana de espera de KycImmediateWebhookBatcher::enqueue().
 * Lee todo lo acumulado en el buffer de la empresa (puede ser una o varias
 * solicitudes, según cuántas hayan capturado su link KYC durante la ventana)
 * y dispara UN SOLO aviso de WhatsApp con `tipo: inmediato`.
 */
final class FlushKycImmediateWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly int $companyId,
        public readonly string $casaSoftware,
        public readonly ?string $whatsapp,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(
        KycImmediateWebhookBatcher $batcher,
        KycWebhookNotifierContract $webhookNotifier,
        SafePemLogger $logger,
    ): void {
        $solicitudes = $batcher->pullBuffer($this->companyId);

        if ($solicitudes === []) {
            // Caso borde: el buffer ya fue vaciado por otro flush, o expiró.
            $logger->info('viafirma.kyc_webhook_flush.empty_buffer', [
                'company_id' => $this->companyId,
            ]);
            return;
        }

        $webhookNotifier->notify(
            casaSoftware: $this->casaSoftware,
            whatsapp: $this->whatsapp,
            solicitudes: $solicitudes,
            tipo: 'inmediato',
        );

        $logger->info('viafirma.kyc_webhook_flush.done', [
            'company_id' => $this->companyId,
            'count'      => count($solicitudes),
        ]);
    }
}
