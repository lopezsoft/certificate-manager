<?php

namespace App\Notifications;

use App\Models\Events\EventMaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceptionEventFailedNotification extends Notification
{
    use Queueable;
    protected EventMaster $event;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        EventMaster $event,
        public $error = null,
    )
    {
        $this->event = $event;
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
        $event              = $this->event;
        $company            = $event->company;
        $documentReception  = $event->documentReception;
        $person             = $documentReception->people;
        $resolution         = $event->resolution;
        return (new MailMessage)
            ->subject("Error al enviar el evento: {$event->description} - {$company->company_name} - {$documentReception->folio}")
            ->line("Estimado(a), **{$company->company_name}**, la DIAN ha generado error al validar el evento **{$event->description}**.")
            ->line("El evento **{$resolution->prefix}{$event->event_number}** no fue enviado correctamente.")
            ->line("Proveedor: **{$person->company_name}**-{$person->dni}")
            ->line("**CUFE:** {$documentReception->cufe_cude}")
            ->line("Número de documento: **{$documentReception->folio}**")
            ->line("Error: **{$this->error}**")
            ->line('Si el problema persiste, por favor contacta al soporte técnico.');
    }
}
