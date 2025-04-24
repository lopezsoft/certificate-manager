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

class SendInvoiceByMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 2; // Intentar 2 veces
    public array $backoff = [60, 180, 600]; // Esperar 1 min, 3 min, 10 min entre reintentos
    /**
     * Create a new message instance.
     */
    public function __construct(
        public $messageData)
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
        $envelope = new Envelope(
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
                'X-DNI-COMPANY' => $this->messageData->company->dni,
                'X-CUSTOMER-DNI' => $this->messageData->customer->dni,
                'X-DOCUMENT-ID' => $this->messageData->document_id,
                'X-TYPE-DOCUMENT-ID' => $this->messageData->type_document_id
            ],
        );
    }


    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.documents.invoice',
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
        $sendPdf = env('SEND_PDF_TO_EMAIL', false);
        if($this->messageData->pdf and $sendPdf) {
            $documentName   = $this->messageData->documentName;
            $data           = base64_decode($this->messageData->pdf);
            $attachments[]  = Attachment::fromData(fn () => $data, "{$documentName}.pdf")->withMime('application/pdf');
        }
        if($this->messageData->attached) {
            $documentName   =  $this->messageData->attachedName;
            $data           = base64_decode($this->messageData->attached);
            $attachments[]  = Attachment::fromData(fn () => $data, "{$documentName}")->withMime('application/zip');
        }
        return $attachments;
    }
}
