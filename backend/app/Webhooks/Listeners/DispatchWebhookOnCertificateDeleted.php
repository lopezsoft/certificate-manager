<?php

namespace App\Webhooks\Listeners;

use App\Events\CertificateRequestDeleted;
use App\Webhooks\Events\CertificateDeletedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhookOnCertificateDeleted implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'webhooks';

    public function handle(CertificateRequestDeleted $event): void
    {
        DeliverWebhookJob::dispatch(
            new CertificateDeletedWebhookEvent(
                certificateRequestId: $event->certificateRequestId,
                companyId: $event->companyId,
                dni: $event->dni,
                companyName: $event->companyName,
            )
        );
    }
}
