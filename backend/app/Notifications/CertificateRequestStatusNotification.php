<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateRequestStatusNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public object $messageData
    )
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Notificación de estado de solicitud de certificado")
            ->greeting("Hola, {$this->messageData->company->company_name}.")
            ->line("El estado de la solicitud ha cambiado a: {$this->messageData->request_status}")
            ->line("Cliente de la solicitud: {$this->messageData->data->company_name}")
            ->line("Comentarios: {$this->messageData->comments}")
            ->action('Abrir sistema', url('certs.matias-api.com'))
            ->line("Esto es solo un aviso, no es necesario responder.");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
