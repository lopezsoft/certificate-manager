<?php

namespace App\Mail;

use App\Notifications\MailFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 2; // Intentar 2 veces
    public array $backoff = [60, 180, 600]; // Esperar 1 min, 3 min, 10 min entre reintentos
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public $messageData)
    {
    }

    /**
     * Handle a failed job.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        // Dirección de correo a la que se enviará la notificación
        $email = $this->messageData->notificationMail;
        if (!empty($email)) {
            // Enviar la notificación por correo electrónico
            Notification::route('mail', $email)->notify(new MailFailedNotification($exception, $this->messageData));
        }
        $MAIL_SUPPORT_ADDRESS = env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co');
        $SEND_MAIL_TO_SUPPORT = env('SEND_MAIL_TO_SUPPORT', false);
        if ($SEND_MAIL_TO_SUPPORT) {
            // Enviar la notificación por correo electrónico al soporte
            Notification::route('mail', $MAIL_SUPPORT_ADDRESS)->notify(new MailFailedNotification($exception, $this->messageData));
        }
    }
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $emailFrom = $this->messageData->email_from ?? env('MAIL_FROM_ADDRESS');
        $envelope =  new Envelope(
            from: new Address($emailFrom, $this->messageData->company->company_name),
            subject: $this->messageData->subject,
        );
        if (!empty($this->messageData->replyTo)) {
            $envelope->replyTo(new Address($this->messageData->replyTo, $this->messageData->company->company_name));
        }
        return $envelope;
    }
    /**
     * get the message headers.
     */
    public function headers(): headers
    {
        return new Headers(
            text: [
                'X-SES-CONFIGURATION-SET' => env('X_SES_CONFIGURATION_SET'),
                'X-DNI-COMPANY' => $this->messageData->company->dni
            ],
        );
    }
    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.documents.custom-email',
        );
    }
    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->messageData->documents as $document) {
            $attachments[] = Attachment::fromData(fn () => base64_decode($document['content']), $document['name'])->withMime($document['mime']);
        }
        return $attachments;
    }
}
