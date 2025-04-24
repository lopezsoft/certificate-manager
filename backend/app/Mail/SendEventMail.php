<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class SendEventMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5; // Intentar 5 veces
    public array $backoff = [60, 180, 600]; // Esperar 1 min, 3 min, 10 min entre reintentos
    /**
     * Create a new message instance.
     */
    public function __construct(
        public $messageData
    )
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $emailFrom = $this->messageData->email_from ?? env('MAIL_FROM_ADDRESS');
        return new Envelope(
            from: new Address($emailFrom, $this->messageData->company->company_name),
            replyTo: [new Address($this->messageData->replyTo, $this->messageData->company->company_name)],
            subject: $this->messageData->subject,
        );
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
            markdown: 'emails.events.send',
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
        if($this->messageData->zipPath) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->messageData->zipPath);
        }
        return $attachments;
    }
}
