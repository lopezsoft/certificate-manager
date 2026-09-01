<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Mail\Mailables\Attachment;

class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
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
                    ->cc(env('MAIL_CC_ADDRESS', 'registro@matias.com.co'))
                    ->subject('Bienvenido a Maticerts')
                    ->greeting('¡Hola ' . $notifiable->first_name . '!')
                    ->line('Gracias por registrarte en Maticerts. Tu cuenta ha sido verificada exitosamente.')
                    ->line('Para comenzar, te invitamos a revisar el manual de usuario y nuestra documentación.')
                    ->action('Ver documentación', 'https://docs.maticerts.com/')
                    ->line('Encontrarás el manual completo de usuario adjunto a este correo.')
                    ->line('Si tienes alguna duda, no dudes en contactar a nuestro equipo de soporte.')
                    ->attach(
                        Attachment::fromPath(public_path('assets/Manual Completo de Usuario Maticerts.pdf'))
                            ->as('Manual_Maticerts.pdf')
                            ->withMime('application/pdf')
                    );
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
