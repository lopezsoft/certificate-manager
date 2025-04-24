<?php

namespace App\Services\Mail;

use Illuminate\Mail\Mailable;
use Exception;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SMTPMailer
{
    protected Mailer $mailer;

    /**
     * Configurar el transporte SMTP utilizando la configuración proporcionada.
     */
    public function __construct(SMTPConfig $config)
    {
        // Construir el DSN para Symfony Mailer usando el objeto SMTPConfig
        $dsn = sprintf('smtp://%s:%s@%s:%d?encryption=%s', urlencode($config->username),
            str_replace('+', '%20', urlencode($config->password)), $config->host, $config->port, $config->encryption);
        $transport = Transport::fromDsn($dsn);

        // Instancia del Mailer
        $this->mailer = new Mailer($transport);
    }

    /**
     * Enviar un correo electrónico utilizando el objeto EmailMessage.
     * @throws Exception
     */
    public function send(EmailMessage $message): void
    {
        try {
            // Crear el email usando el objeto EmailMessage
            $email = (new Email())
                ->from($message->from)
                ->to($message->to)
                ->subject($message->subject)
                ->html($message->htmlBody);

            // Agregar CC y BCC si existen
            if (!empty($message->cc)) {
                $email->cc($message->cc);
            }
            if (!empty($message->bcc)) {
                $email->bcc($message->bcc);
            }

            // Adjuntar archivos si los hay
            foreach ($message->attachments as $filePath) {
                $email->attachFromPath($filePath);
            }

            // Enviar el correo
            $this->mailer->send($email);
        } catch (Exception $e) {
           throw new Exception($e->getMessage());
        }
    }

    /**
     * Enviar un correo electrónico utilizando un Mailable.
     * @throws Exception
     */
    public function sendMailable(Mailable $mailable, $to): void
    {
        try {
            // Renderizar el contenido del Mailable (HTML)
            $htmlBody = $mailable->render();

            // Obtener el asunto y el remitente desde el Mailable
            $from       = $mailable->from[0]['address'] ?? config('mail.from.address');
            $fromName   = $mailable->from[0]['name'] ?? config('mail.from.name');
            $subject    = $mailable->subject ?? config('mail.from.name');
            // Adjuntar archivos si los hay
            $attachments = $mailable->rawAttachments ?? [];

            // Crear el correo electrónico con Symfony Mailer
            $email = (new Email())
                ->from(new Address($from, $fromName))
                ->to($to)
                ->subject($subject)
                ->html($htmlBody);

            // Agregar archivos adjuntos si los hay
            foreach ($attachments as $attachment) {
                $email->attach($attachment['data'], $attachment['name'], $attachment['options']['mime']);
            }

            // Enviar el correo
            $this->mailer->send($email);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
