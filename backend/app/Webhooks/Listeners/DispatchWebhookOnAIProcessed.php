<?php

namespace App\Webhooks\Listeners;

use App\Events\CertificateProcessedWithAI;
use App\Models\CertificateRequest;
use App\Webhooks\Events\CertificateAIProcessedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhookOnAIProcessed implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'webhooks';

    public function handle(CertificateProcessedWithAI $event): void
    {
        $companyId = CertificateRequest::find($event->certificateRequestId)?->company_id;

        if ($companyId === null) {
            return;
        }

        DeliverWebhookJob::dispatch(
            new CertificateAIProcessedWebhookEvent(
                certificateRequestId: $event->certificateRequestId,
                companyId: $companyId,
                fileId: $event->fileId,
                aiResults: $event->aiResults,
                processingTime: $event->processingTime,
            )
        );
    }
}
