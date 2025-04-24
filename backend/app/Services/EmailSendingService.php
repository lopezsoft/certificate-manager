<?php

namespace App\Services;

use App\Common\ImageNotification;
use App\Models\business\Customer;
use App\Models\JsonData;
use App\Models\Types\TypeCurrency;
use App\Models\Types\TypeDocument;
use App\Modules\Documents\MailMessages;
use App\Modules\Documents\SendEmail;
use App\Services\Mail\EmailSuppressedNotificationService;
use Exception;
use Illuminate\Support\Facades\DB;

class EmailSendingService
{
    /**
     * @throws Exception
     */
    public static function send($params): void
    {
        try {

            $company    = $params->company;
            $request    = $params->request;
            $shipping   = $params->shipping;
            $jsonData   = JsonData::query()->where('shipping_id', $shipping->id)->first();
            $jsonData   = (object) $jsonData->jdata;
            $settings   = collect($company->settings ?? []);
            $senderMail = null;
            $canSendCopyMail = false;
            $useSenderMail   = false;
            $replyTo         = null;
            $notificationMail = null;
            if(!isset($jsonData->currencyId)) {
                $currency = (object) $jsonData->currency;
                $jsonData->currencyId = $currency->id ?? 272;
            }
            $currency           = TypeCurrency::query()->where('id', $jsonData->currencyId ?? 272);
            $typeDocument       = TypeDocument::query()->where('id', $jsonData->typeDocumentId);
            $customer           = $shipping->jsonData->customer;
            $jsonData->currency     = $currency->first();
            $jsonData->typeDocument = $typeDocument->first();
            $jsonData->customer     = $customer;
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
                    $canSendCopyMail = (int)$setting->value === 1;
                }
                if ($setting->setting->key_value === 'USESENDERMAIL') {
                    $useSenderMail = (int)$setting->value === 1;
                }
            }
            // Cuerpo del mensaje del correo electrónico
            $message            = MailMessages::getElectronicDocumentMessage($request, $company, $jsonData, $shipping);
            if(!AttachedDocumentService::isExists($shipping) ||
                (!PdfDocumentService::isExists($shipping) && (in_array($request->type_id, [1, 3, 4, 5, 6], true)))){
                return;
            }
            ImageNotification::empty($company, $notificationMail);
            $message->replyTo   = $replyTo;
            $message->notificationMail = $notificationMail;
            $emailTo    = $params->emailTo;
            EmailSuppressedNotificationService::checkSuppressed($emailTo, $company, $message->subject);
            $data       = [
                'company_id'            => $company->id,
                'shipping_history_id'   => $shipping->id,
                'emails'                => $emailTo,
            ];
            DB::beginTransaction();
            /**
             * Envío del correo al Cliente
             * El correo se envía a la dirección de correo electrónico del cliente
             * Desde la configuración de la empresa se obtiene el correo electrónico del remitente
             */
            if (!empty($senderMail) && $useSenderMail) {
                $message->email_from = $senderMail;
            }
            SendEmail::send($message,  $emailTo, $data);
            /**
             * Envío al correo de la empresa emisora
             * El correo se envía a la dirección de correo electrónico de la empresa emisora
             * Si tiene la opción de enviar copia del correo electrónico activada
             */
            if ($canSendCopyMail) {
                $message->email_from = (!empty($senderMail) && $useSenderMail) ? $senderMail : 'no-reply@matiasinbox.com.co';
                SendEmail::send($message, $company->email, $data);
            }
            DB::commit();
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
