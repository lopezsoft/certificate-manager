<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de acreditación KYC pendiente (V-308).
 *
 * Se almacena en canal `database` para aparecer en el bell del frontend
 * (cuando se notifica a un `User`), y en canal `mail` para avisar a la
 * empresa dueña de la solicitud (vía `Notification::route('mail', ...)`,
 * que usa un `AnonymousNotifiable` sin soporte de canal `database`).
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
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return ['database', 'mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Verificación de identidad pendiente — {$this->companyName}")
            ->greeting('Hola,')
            ->line("Se generó el link de verificación de identidad (KYC) para la solicitud de certificado de {$this->companyName}.")
            ->line('Reenvíelo a la persona que debe completar la verificación — debe tener a mano su cédula o pasaporte.')
            ->line('Enlace (copie y pegue para reenviar):')
            ->line($this->kycUrl)
            ->action('Iniciar verificación de identidad', $this->kycUrl)
            ->line('Si no reconoce esta solicitud, puede ignorar este mensaje.');
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
