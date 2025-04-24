<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EmailSuppressedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $messageData,
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
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $messageData        = $this->messageData;
        $suppressedEmail    = $messageData->suppressedEmail;
        $reasonType         = $messageData->reasonType;
        $reasonSubtype      = $messageData->reasonSubtype ?? null;
        $diagnosticCode     = $messageData->diagnosticCode ?? null;
        $emailLog           = $messageData->emailLog;
        $company            = $emailLog->company;
        $customer           = $emailLog->customer;
        $documentType       = $emailLog->typeDocument;
        $shippingHistory = DB::table('shipping_history')
            ->where('id', $emailLog->document_id)
            ->first();
        $subject = "⚠️ Falla de entrega de correo a: {$suppressedEmail}";
        $line1 = "No fue posible entregar el correo enviado a la dirección **{$suppressedEmail}**.";
        $line2 = "Razón: **{$reasonType}**" . ($reasonSubtype ? " ({$reasonSubtype})" : "");
        // Podrías añadir info del documento/cliente si la tienes en $this->emailLog
        $line3 = "Esta dirección de correo(**{$suppressedEmail}**) ha sido añadida a la lista de supresión y no se le enviarán más correos desde el sistema.";
        $line4 = "Por favor, contacta al destinatario para obtener una dirección válida si es necesario, especialmente si se trataba de un documento oficial.";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hola, {$company->company_name}!")
            ->line($line1)
            ->line($line2)
            ->line($line3)
            ->line($line4)
            ->lineIf(($documentType && $shippingHistory), "Tipo de documento: **{$documentType->voucher_name}** Nº. {$shippingHistory->document_number}")
            ->lineIf($customer, "Cliente: **{$customer->company_name}**")
            ->line("**Detalles del correo:**: {$diagnosticCode}");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $messageData        = $this->messageData;
        $suppressedEmail    = $messageData->suppressedEmail;
        $reasonType         = $messageData->reasonType;
        $reasonSubtype      = $messageData->reasonSubtype ?? null;
        $emailLog           = $messageData->emailLog;
        $customer           = $emailLog->customer;
        return [
            'email_log_id' => $emailLog->id,
            'suppressed_email' => $suppressedEmail,
            'reason_type' => $reasonType,
            'reason_subtype' => $reasonSubtype,
            'message' => "Fallo de entrega ({$reasonType}) a {$suppressedEmail}. Dirección añadida a supresión.",
            'customer_name' => $customer->company_name ?? null
        ];
    }

    /**
     * Get the array representation of the notification for the database.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
