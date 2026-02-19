<?php

namespace App\Webhooks\Listeners;

use App\Events\CertificateStatusChanged;
use App\Webhooks\Events\CertificateStatusChangedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhookOnStatusChanged implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'webhooks';

    public function handle(CertificateStatusChanged $event): void
    {
        DeliverWebhookJob::dispatch(
            new CertificateStatusChangedWebhookEvent(
                certificateRequestId: $event->certificateRequestId,
                companyId: $event->companyId,
                previousStatus: $event->previousStatus,
                newStatus: $event->newStatus,
                userId: $event->userId,
                comment: $event->comment,
            )
        );
    }
}
