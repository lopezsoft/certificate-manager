<?php

namespace App\Webhooks\Jobs;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public int   $timeout = 30;
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly WebhookEventContract $webhookEvent,
    ) {
        $this->onQueue(config('webhooks.queue', 'webhooks'));
    }

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->webhookEvent);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DeliverWebhookJob failed permanently', [
            'event_type' => $this->webhookEvent->eventType(),
            'company_id' => $this->webhookEvent->companyId(),
            'error'      => $exception->getMessage(),
        ]);
    }
}
