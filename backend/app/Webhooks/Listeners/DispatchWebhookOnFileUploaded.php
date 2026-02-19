<?php

namespace App\Webhooks\Listeners;

use App\Events\CertificateFileUploaded;
use App\Webhooks\Events\CertificateFileUploadedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhookOnFileUploaded implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'webhooks';

    public function handle(CertificateFileUploaded $event): void
    {
        DeliverWebhookJob::dispatch(
            new CertificateFileUploadedWebhookEvent(
                certificateRequestId: $event->certificateRequestId,
                companyId: $event->companyId,
                fileId: $event->fileId,
                fileName: $event->fileName,
                documentType: $event->documentType,
            )
        );
    }
}
