<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Common\ImageNotification;
use App\Common\MessageExceptionResponse;
use App\Interfaces\DocumentsInterface;
use App\Jobs\Emails\SendMailJob;
use App\Modules\Company\CompanyQueries;
use App\Services\Mail\EmailSuppressedNotificationService;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SendingEmailDocuments implements DocumentsInterface
{
    use HasFactory;

    public function process(Request $request, $trackId): JsonResponse
    {

        try {
            $company        = CompanyQueries::getCompany();
            $emailTo        = $request->input('email_to');
            if(is_null($emailTo) || !(strlen($emailTo) > 10)) {
                throw new Exception('Debe indicar, al menos un correo, a donde desea enviar el documento.', 400);
            }
            $emailTo    = str_replace(' ', '', $emailTo);
            EmailSuppressedNotificationService::checkSuppressed($emailTo, $company, 'Envío de documento electrónico');
            $settings   = collect($company->settings ?? []);
            $senderMail = null;
            $isSendmail = false;
            $replyTo        = null;
            $useSenderMail  = false;
            $notificationMail = null;
            foreach ($settings as $setting) {
                if ($setting->setting->key_value === 'SENDEREMAIL') {
                    $senderMail = $setting->value;
                }
                if ($setting->setting->key_value === 'REPLYTOMAIL') {
                    $replyTo = $setting->value;
                }
                if ($setting->setting->key_value === 'NOTIFICATIONEMAIL') {
                    $notificationMail = $setting->value;
                }
                if ($setting->setting->key_value === 'SENDMAILCOPY') {
                    $isSendmail = (int)$setting->value === 1;
                }
                if ($setting->setting->key_value === 'USESENDERMAIL') {
                    $useSenderMail = (int)$setting->value === 1;
                }
            }

            $companyEmail       = str_replace(';', ',', $company->email);
            $logo               = ImageNotification::empty($company, $notificationMail);
            $message       = (Object) [
                'documents'     => $request->input('documents') ?? [],
                'subject'       => $request->input('subject') ?? 'Titulo del correo',
                'title'         => $request->input('title') ?? 'Titulo del correo',
                'message'       => $request->input('message') ?? 'Mensaje del correo',
                'company_image' => $logo,
                'replyTo'       => $replyTo,
                'company'       => $company,
                'notificationMail' => $notificationMail,
                'email_from'    => null,
            ];

            $data       = [
                'company_id'    => $company->id,
                'emails'        => $emailTo
            ];
            /**
             * Envío del correo al Cliente
             * El correo se envía a la dirección de correo electrónico del cliente
             * Desde la configuración de la empresa se obtiene el correo electrónico del remitente
             */
            if (!empty($senderMail) && $useSenderMail) {
                $message->email_from = $senderMail;
            }
            SendmailJob::dispatch($message,  $emailTo, $data, 'email_sending');
            /**
             * Envío al correo de la empresa emisora
             * El correo se envía a la dirección de correo electrónico de la empresa emisora
             * Si tiene la opción de enviar copia del correo electrónico activada
             */
            if ($isSendmail) {
                $message->email_from = (!empty($senderMail) && $useSenderMail) ? $senderMail : 'no-reply@matiasinbox.com.co';
                SendmailJob::dispatch($message, $companyEmail, $data, 'email_sending');
            }
            return HttpResponseMessages::getResponse(["message" => 'Se ha iniciado el proceso de envío del correo electrónico.']);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

}
