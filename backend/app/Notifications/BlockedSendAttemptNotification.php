<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BlockedSendAttemptNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected $messageData,
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $messageData        = $this->messageData;
        $originalSubject    = $messageData->originalSubject ?? 'Asunto no disponible';
        $suppressedEmails   = $messageData->suppressedEmails;
        $company            = $messageData->company;
        $greeting           = "Hola {$company->company_name}!,\n\n";

        // Construye el cuerpo del mensaje
        $line1 = "Se intentó enviar un correo con el asunto: \"**{$originalSubject}**\".";
        $line2 = "El envío fue **bloqueado automáticamente** porque la(s) siguiente(s) dirección(es) de
         destinatario(s) se encuentra(n) en nuestra lista de supresión (debido a rebotes permanentes o quejas de spam anteriores):";

        // Formatea la lista de correos suprimidos
        $suppressedList = '';
        foreach($suppressedEmails as $email) {
            $suppressedList .= "\n* " . $email; // Usa formato Markdown de lista
        }
        $line3 = "Dirección(es) afectada(s):" . $suppressedList;

        $subject            = "🚫 Envío Bloqueado a Dirección(es) Suprimida(s) - ".$suppressedList; // Asunto claro


        $line5 = "\nComo resultado, el correo **no fue enviado**. Es importante no intentar enviar nuevamente a estas
        direcciones hasta que sean verificadas o corregidas.";
        $line6 = "**Acción recomendada:** Por favor, contacta al cliente/destinatario asociado a esta(s) dirección(es)
        para obtener una dirección de correo válida y actualízala en tu aplicativo.";

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($line1)
            ->line($line2)
            ->line($line3) // Lista de correos suprimidos
            ->line($line5)
            ->line($line6);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $messageData = $this->messageData;
        $suppressedEmails = $messageData->suppressedEmails;
        $originalSubject = $messageData->originalSubject;
        return [
            'suppressed_emails' => $suppressedEmails,
            'original_subject' => $originalSubject,
            'message' => "Envío bloqueado para el asunto '{$originalSubject}'. Destinatarios suprimidos: " .
                implode(', ', $suppressedEmails) . ". Revisa y actualiza la dirección.",
        ];
    }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
