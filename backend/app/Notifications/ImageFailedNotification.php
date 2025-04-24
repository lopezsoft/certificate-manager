<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImageFailedNotification extends Notification
{
    use Queueable;
    protected Company $company;

    /**
     * Create a new notification instance.
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
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
            ->subject("Error al enviar el correo, falta logotipo - {$this->company->company_name}")
            ->line("Estimado(a), **{$this->company->company_name}**, ha ocurrido un error al intentar enviar el correo electrónico.")
            ->line('El **logotipo** del negocio no se encuentra disponible, por favor verifica la configuración de la empresa.')
            ->line('El **logotipo** del negocio es necesario para enviar el correo electrónico.')
            ->line('Por favor, agrega la imagen del logotipo del negocio, en el menú **Perfil->Empresa** y luego reenvía el correo nuevamente.')
            ->line('Si el problema persiste, por favor contacta al soporte técnico.');
    }
}
