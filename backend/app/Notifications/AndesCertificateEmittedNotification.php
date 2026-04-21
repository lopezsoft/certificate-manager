<?php

namespace App\Notifications;

use App\Andes\Models\AndesCertificateRequest;
use App\Models\CertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AndesCertificateEmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AndesCertificateRequest $andesCertRequest,
        private readonly CertificateRequest      $certRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vigencia    = $this->andesCertRequest->vigencia_cert === 3 ? '1 año' : '2 años';
        $tipoCert    = $this->andesCertRequest->tipo_cert === 10
            ? 'Persona Jurídica'
            : 'Persona Natural';

        return (new MailMessage)
            ->subject('🎉 Tu certificado digital ha sido emitido — ' . $this->andesCertRequest->certificate_serial)
            ->greeting('¡Hola, ' . ($notifiable->name ?? 'estimado usuario') . '!')
            ->line('Tu certificado digital de firma electrónica ha sido emitido exitosamente por ANDES SCD.')
            ->line('**Datos del certificado:**')
            ->line('- Serial: ' . $this->andesCertRequest->certificate_serial)
            ->line('- Tipo: Facturación Electrónica — ' . $tipoCert)
            ->line('- Vigencia: ' . $vigencia)
            ->line('- Fecha de emisión: ' . $this->andesCertRequest->emitted_at?->format('d/m/Y H:i'))
            ->action('Ver solicitud', url('/dashboard'))
            ->line('Gracias por usar Certificate Manager — LOPEZSOFT.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'andes_certificate_request_id' => $this->andesCertRequest->id,
            'certificate_request_id'       => $this->andesCertRequest->certificate_request_id,
            'certificate_serial'           => $this->andesCertRequest->certificate_serial,
        ];
    }
}

