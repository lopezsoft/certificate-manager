<?php

namespace App\Listeners;

use App\Andes\Events\AndesIdentityValidated;
use App\Webhooks\Events\AndesIdentityValidatedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * LogAndesIdentityValidated
 *
 * Escucha el evento AndesIdentityValidated y:
 * 1. Registra la validación exitosa de identidad en los logs.
 * 2. Despacha el webhook al endpoint registrado de la empresa.
 */
class LogAndesIdentityValidated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(AndesIdentityValidated $event): void
    {
        $validation = $event->validation;

        Log::info('ANDES identity validated successfully', [
            'validation_id'                => $validation->id,
            'andes_certificate_request_id' => $validation->andes_certificate_request_id,
            'validation_type'              => $validation->validation_type,
            'estado'                       => $validation->estado,
            'validated_at'                 => $validation->validated_at?->toIso8601String(),
        ]);

        DeliverWebhookJob::dispatch(
            new AndesIdentityValidatedWebhookEvent($validation)
        );
    }
}

