<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de "último llamado" — la solicitud vence en menos de 24h sin que el
 * usuario final complete la verificación KYC. Si no se completa a tiempo,
 * ExpireStalledKycAccreditationsJob cancelará la solicitud y reintegrará el
 * cupo. El link sigue siendo válido en este punto.
 */
final class ViafirmaKycLastCallNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $viafirmaRequestId,
        private readonly string $companyName,
        private readonly string $applicantName,
        private readonly string $codRequest,
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
            ->subject("⚠️ Último llamado — verificación KYC vence pronto ({$this->codRequest})")
            ->greeting('Hola,')
            ->line("La solicitud de {$this->applicantName} ({$this->companyName}) vence en menos de 24 horas sin haber completado la verificación de identidad (KYC).")
            ->line('Si no se completa a tiempo, la solicitud será cancelada automáticamente y el cupo del certificado quedará disponible de nuevo.')
            ->line('Reenvíe el enlace hoy mismo para no perder el cupo:')
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
            'type'                => 'viafirma_kyc_last_call',
            'viafirma_request_id' => $this->viafirmaRequestId,
            'company_name'        => $this->companyName,
            'applicant_name'      => $this->applicantName,
            'cod_request'         => $this->codRequest,
            'kyc_url'             => $this->kycUrl,
            'message'             => "La solicitud {$this->codRequest} de {$this->applicantName} vence en menos de 24h — complete la verificación KYC para no perder el cupo.",
        ];
    }
}
