<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación de acreditación KYC pendiente (V-308).
 *
 * Se almacena en canal `database` para aparecer en el bell del frontend.
 * En Sprint 5 se agregará canal `mail` con plantilla personalizada.
 */
final class ViafirmaAccreditationPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $viafirmaRequestId,
        private readonly string $companyName,
        private readonly string $kycUrl,
    ) {}

    /**
     * @return string[]
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type'                  => 'viafirma_accreditation_pending',
            'viafirma_request_id'   => $this->viafirmaRequestId,
            'company_name'          => $this->companyName,
            'kyc_url'               => $this->kycUrl,
            'message'               => "Tiene una acreditación KYC pendiente para {$this->companyName}. Complete la verificación de identidad para continuar con la emisión del certificado.",
        ];
    }
}
