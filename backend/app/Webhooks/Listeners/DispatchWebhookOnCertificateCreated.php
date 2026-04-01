<?php

namespace App\Webhooks\Listeners;

use App\Events\CertificateRequestCreated;
use App\Webhooks\Events\CertificateCreatedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhookOnCertificateCreated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'webhooks';

    public function handle(CertificateRequestCreated $event): void
    {
        DeliverWebhookJob::dispatch(
            new CertificateCreatedWebhookEvent(
                certificateRequestId: $event->certificate->id,
                companyId: $event->certificate->company_id,
                data: $event->certificate->toArray(),
            )
        );
    }
}
