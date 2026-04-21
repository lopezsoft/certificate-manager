<?php

namespace App\Payments\Jobs;

use App\Quotas\Services\PaymentOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWompiWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly array $event,
    ) {}

    public function handle(PaymentOrchestrator $orchestrator): void
    {
        Log::info('[WOMPI-WEBHOOK] Procesando evento.', [
            'event' => $this->event['event'] ?? 'unknown',
        ]);

        $orchestrator->processWebhookEvent($this->event);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[WOMPI-WEBHOOK] Job fallido.', ['error' => $e->getMessage()]);
    }
}

