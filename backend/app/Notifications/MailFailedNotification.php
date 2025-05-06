<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class MailFailedNotification extends Notification
{
    use Queueable;


    protected Throwable $exception;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Throwable $exception,
        public $messageData
    )
    {
        $this->exception = $exception;
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
        $company    = $this->messageData->company;
        $customer   = $this->messageData->data;
        return (new MailMessage)
            ->subject("Error al enviar el correo - {$company->company_name}")
            ->line("Hola **{$company->company_name}**, ocurrió un error al intentar enviar un correo")
            ->line("**Cliente:** {$customer->company_name} - {$customer->dni}")
            ->line('**Detalles del error:**')
            ->line($this->exception->getMessage())
            ->line('Por favor, revisa el sistema para más detalles.');
    }
}
