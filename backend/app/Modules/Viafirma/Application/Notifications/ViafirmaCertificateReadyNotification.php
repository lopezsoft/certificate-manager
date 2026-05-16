<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación de certificado P12 listo para descarga (V-407).
 *
 * Canal database — aparece en el bell del frontend.
 * Sprint 5 agregará canal mail con plantilla y link firmado 24h.
 */
final class ViafirmaCertificateReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $viafirmaRequestId,
        private readonly string $companyName,
    ) {}

    /** @return string[] */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type'                  => 'viafirma_certificate_ready',
            'viafirma_request_id'   => $this->viafirmaRequestId,
            'company_name'          => $this->companyName,
            'message'               => "El certificado digital para {$this->companyName} ha sido emitido y está listo para descarga.",
        ];
    }
}
