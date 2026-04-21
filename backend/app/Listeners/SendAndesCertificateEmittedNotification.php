<?php

namespace App\Listeners;

use App\Andes\Events\AndesCertificateEmitted;
use App\Notifications\AndesCertificateEmittedNotification;
use App\Webhooks\Events\AndesCertificateEmittedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * SendAndesCertificateEmittedNotification
 *
 * Escucha el evento AndesCertificateEmitted y:
 * 1. Envía notificación por email al representante legal (usuario de la empresa).
 * 2. Despacha el webhook al endpoint registrado de la empresa.
 */
class SendAndesCertificateEmittedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(AndesCertificateEmitted $event): void
    {
        $andesCertRequest = $event->andesCertificateRequest;
        $andesCertRequest->loadMissing(['certificateRequest.company.user']);
        $certRequest = $andesCertRequest->certificateRequest;

        if ($certRequest?->company?->user) {
            $certRequest->company->user->notify(
                new AndesCertificateEmittedNotification($andesCertRequest, $certRequest)
            );

            Log::info('AndesCertificateEmitted notification sent', [
                'andes_request_id'       => $andesCertRequest->id,
                'certificate_request_id' => $andesCertRequest->certificate_request_id,
                'certificate_serial'     => $andesCertRequest->certificate_serial,
                'company_id'             => $certRequest->company_id,
            ]);
        }

        DeliverWebhookJob::dispatch(
            new AndesCertificateEmittedWebhookEvent($andesCertRequest)
        );
    }
}


