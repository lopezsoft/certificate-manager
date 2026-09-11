<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de cancelación efectiva — el usuario final no completó la
 * verificación KYC dentro del plazo (`viafirma.polling.expiration_hours`).
 * La solicitud fue marcada EXPIRED y el cupo consumido ya fue reintegrado
 * (operación de inventario interno, no una devolución financiera).
 */
final class ViafirmaKycExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $viafirmaRequestId,
        private readonly string $companyName,
        private readonly string $applicantName,
        private readonly string $codRequest,
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
            ->subject("Solicitud cancelada por vencimiento del plazo de verificación ({$this->codRequest})")
            ->greeting('Hola,')
            ->line("La solicitud de {$this->applicantName} ({$this->companyName}) fue cancelada automáticamente porque no se completó la verificación de identidad (KYC) dentro del plazo definido.")
            ->line('El cupo del certificado ya fue reintegrado y está disponible para que generen una nueva solicitud cuando lo necesiten.')
            ->line('Si el usuario ya completó la verificación, puede haber sido justo en el límite del plazo — contáctenos si necesitan revisar el caso.');
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type'                => 'viafirma_kyc_expired',
            'viafirma_request_id' => $this->viafirmaRequestId,
            'company_name'        => $this->companyName,
            'applicant_name'      => $this->applicantName,
            'cod_request'         => $this->codRequest,
            'message'             => "La solicitud {$this->codRequest} de {$this->applicantName} fue cancelada por vencimiento del plazo de verificación KYC. El cupo ya fue reintegrado.",
        ];
    }
}
