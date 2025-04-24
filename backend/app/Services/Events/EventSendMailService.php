<?php

namespace App\Services\Events;

use App\Common\HttpResponseMessages;
use App\Common\ImageNotification;
use App\Common\MessageExceptionResponse;
use App\Mail\SendEventMail;
use App\Models\Events\EventMaster;
use App\Models\Events\MailMessages;
use Exception;
use Illuminate\Support\Facades\Mail;

class EventSendMailService
{
    public  function send($eventId): \Illuminate\Http\JsonResponse
    {
        try {
            $eventMaster    = EventMaster::query()->where('id', $eventId)->first();
            $company        = $eventMaster->company;
            if (!$eventMaster) {
                throw new Exception('No se encontró el evento');
            }
            if ($eventMaster->send_mail >= 4) {
                throw new Exception('Se ha alcanzado el límite de envío de correos. No puede enviar más de 4 correos por evento.', 400);
            }
            $settings           = collect($company->settings ?? []);
            $canSendCopyMail    = false;
            $notificationMail   = null;
            $useSenderMail      = false;
            foreach ($settings as $setting) {
                if ($setting->setting->key_value === 'SENDEREMAIL') {
                    $senderMail = $setting->value;
                }
                if ($setting->setting->key_value === 'SENDMAILCOPY') {
                    $canSendCopyMail = (int)$setting->value === 1;
                }
                if ($setting->setting->key_value === 'NOTIFICATIONEMAIL') {
                    $notificationMail = $setting->value;
                }
                if ($setting->setting->key_value === 'USESENDERMAIL') {
                    $useSenderMail = (int)$setting->value === 1;
                }
            }
            $msg        = MailMessages::getEventMessage($eventMaster);
            ImageNotification::empty($company, $notificationMail);
            $people     = $msg->people;
            /**
             * Envío del correo al Cliente
             * El correo se envía a la dirección de correo electrónico del cliente
             * Desde la configuración de la empresa se obtiene el correo electrónico del remitente
             */
            if (!empty($senderMail) && $useSenderMail) {
                $msg->email_from = $senderMail;
            }

            $send   = new SendEventMail($msg);
            // Envío del correo al proveedor y a la empresa remitente
            Mail::to($people->email)->queue($send);
            /**
             * Envío al correo de la empresa emisora
             * El correo se envía a la dirección de correo electrónico de la empresa emisora
             * Si tiene la opción de enviar copia del correo electrónico activada
             */
            if ($canSendCopyMail) {
                $msg->email_from = (!empty($senderMail) && $useSenderMail) ? $senderMail : 'no-reply@matiasinbox.com.co';
                $send   = new SendEventMail($msg);
                // Envío del correo al proveedor y a la empresa remitente
                Mail::to($company->email)->queue($send);
            }
            // Actualiza el contador de envío de correos
            ++$eventMaster->send_mail;
            $eventMaster->save();
            return HttpResponseMessages::getResponse([
                'message' => 'Correo enviado correctamente'
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
