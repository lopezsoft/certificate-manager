<?php

namespace App\Services\Mail;

use App\Classes\CompanyClass;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Jobs\Mail\EmailSendingSMTPJob;
use App\Mail\EmailTest;
use App\Models\EmailConfig;
use App\Modules\ElectronicDocuments\InvoiceCustomer;
use App\Modules\ElectronicDocuments\MailMessages;
use App\Modules\ElectronicDocuments\SendMail;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmailSendingService
{
    /**
     * @throws Exception
     */
    public static function send($params): void
    {
        try {

            $company            = $params->company;
            $user               = $params->user;
            $customerId         = $params->customerId;
            $settings           = collect($company->settings ?? []);
            $document           = $params->document;
            $emailData          = $params->emailData;
            $emailTable         = $params->emailTable;
            $senderMail         = null;
            $isSendmail         = false;
            $isSendmailFromSmtp = false;
            $replyTo            = null;
            $notificationMail   = null;
            foreach ($settings as $setting) {
                if ($setting->setting->key_value === 'SENDEREMAIL') {
                    $replyTo    = $setting->value;
                    $senderMail = $setting->value;
                }
                if ($setting->setting->key_value === 'NOTIFICATIONEMAIL') {
                    $notificationMail = $setting->value;
                }
                if ($setting->setting->key_value === 'SENDMAILCOPY') {
                    $isSendmail = intval($setting->value) === 1;
                }
                if ($setting->setting->key_value === 'SENDEREMAILSMTP') {
                    $isSendmailFromSmtp = intval($setting->value) === 1;
                }
            }

            $customer   = InvoiceCustomer::getCustomer($customerId);
            // Cuerpo del mensaje del correo electrónico
            $message    = MailMessages::getElectronicDocumentMessage($company, $document, $customer, $user);

            $cloud      = Storage::cloud();
            /**
             * Se verifica si la representación gráfica existe.
             * Si no existe se corta el proceso y se retorna null
             */
            if(!$cloud->exists($document->path_report) || !$cloud->exists($message->filePath)){
                return;
            }

            if ($replyTo) {
                $message->replyTo   = $replyTo;
            }
            if (!$notificationMail) {
                $notificationMail = $company->email;
            }
            $message->notificationMail = $notificationMail;

            // Correo personalizado para el envío de la factura electrónica al cliente
            if($isSendmailFromSmtp) {
                $emailConfig = EmailConfig::query()
                    ->where('active', true)
                    ->where('company_id', $company->id)
                    ->first();
                if ($emailConfig) {
                    CleanEmailsJob::clean($company, $document->id, $document->type_document_id);
                    $message->email_from    = $emailConfig->username;
                    $message->replyTo       = $emailConfig->username;
                    self::sentToSMTP($message, $customer->email, $emailConfig);
                } else {
                    $isSendmailFromSmtp = false;
                }

            }
            if (!empty($senderMail) && $company->verified_email === 1) {
                $message->email_from = $senderMail;
            }
            if(!$isSendmailFromSmtp) {
                SendMail::invoice($message,  $customer->email, $emailData, $emailTable);
            }

            // Si se activa la copia del correo a la empresa emisora, se envía una copia
            if ($isSendmail) {
                $message->email_from = 'no-reply@matiasinbox.com.co';
                // Envío al correo de la empresa emisora
                SendMail::invoice($message, $company->email, $emailData, $emailTable);
            }
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function createSMTP(Request $request): JsonResponse
    {
        try {
            $company = CompanyClass::getCompany($request);
            $records = json_decode($request->input('records'));
            if (empty($records)) {
                throw new Exception('No se han enviado los datos necesarios para la configuración del correo electrónico.');
            }
            $emailConfig = new EmailConfig(array(
                'driver'        => 'smtp',
                'company_id'    => $company->id,
                'host'          => $records->host,
                'port'          => $records->port,
                'encryption'    => $records->encryption,
                'username'      => $records->username,
                'password'      => $records->password,
                'from_address'  => $records->from_address ?? $records->username,
                'from_name'     => $records->from_name ?? explode('@', $records->username)[0],
            ));

            $emailConfig->setPasswordAttribute($records->password);
            $emailConfig->save();
            return HttpResponseMessages::getResponse([
                'message' => 'Configuración de correo electrónico SMTP creada correctamente.',
                'data'    => $emailConfig
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function updateSMTP(Request $request): JsonResponse
    {
        try {
            $company = CompanyClass::getCompany($request);
            $records = json_decode($request->input('records'));
            if (empty($records)) {
                throw new Exception('No se han enviado los datos necesarios para la configuración del correo electrónico.');
            }
            $emailConfig = EmailConfig::where('company_id', $company->id)->first();
            if (empty($emailConfig)) {
                throw new Exception('No se ha encontrado la configuración de correo electrónico SMTP.');
            }
            $emailConfig->update([
                'host'          => $records->host,
                'port'          => $records->port,
                'encryption'    => $records->encryption,
                'username'      => $records->username,
                'password'      => $records->password,
                'from_address'  => $records->from_address ?? $emailConfig->username,
                'from_name'     => $records->from_name ?? explode('@', $emailConfig->username)[0],
                'active'        => false,
            ]);
            $emailConfig->setPasswordAttribute($records->password);
            $emailConfig->save();
            return HttpResponseMessages::getResponse([
                'message' => 'Configuración de correo electrónico SMTP actualizada correctamente.',
                'data'    => $emailConfig
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function getSMTP(Request $request): JsonResponse
    {
        try {
            $company = CompanyClass::getCompany($request);
            $emailConfig = EmailConfig::query()
                ->selectRaw('id, driver, host, port, encryption, username, from_address, from_name, active')
                ->where('company_id', $company->id)->first();
            return HttpResponseMessages::getResponse([
                'message' => 'Configuración de correo electrónico SMTP obtenida correctamente.',
                'dataRecords'   => [
                    'data' => [$emailConfig]
                ]
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function sendTestSMTP(Request $request): JsonResponse
    {
        try {
            $company        = CompanyClass::getCompany($request);
            $emailConfig    = EmailConfig::where('company_id', $company->id)->first();
            if (empty($emailConfig)) {
                throw new Exception('No se ha encontrado la configuración de correo electrónico SMTP.');
            }
            $emailConfig->active = false;
            $emailConfig->save();
            $email      = $request->input('email'); // Correo electrónico de prueba
            if (empty($email)) {
                throw new Exception('No se ha enviado el correo electrónico de prueba.');
            }

            $email = EmailSettingService::sanitizeEmailAddress($email);
            $smtpConfig = (object)[
                'host'          => $emailConfig->host,
                'port'          => $emailConfig->port,
                'encryption'    => $emailConfig->encryption,
                'username'      => $emailConfig->username,
                'password'      => $emailConfig->password,
                'from_address'  => $emailConfig->username,
                'from_name'     => $emailConfig->from_name,
            ];

            $smtpSettings = new SMTPConfig($smtpConfig->host, $smtpConfig->port, $smtpConfig->username, $smtpConfig->password, $smtpConfig->encryption);
            $symfonyMailer = new SMTPMailer($smtpSettings);

            // Establecer la dirección y el nombre 'from' para el correo electrónico
            $params = (object)[
                'email_from' => EmailSettingService::sanitizeEmailAddress($emailConfig->from_address),
                'from_name' => $emailConfig->from_name,
            ];
            $symfonyMailer->sendMailable(new EmailTest($params), $email);
            $emailConfig->active = true;
            $emailConfig->save();
            return HttpResponseMessages::getResponse([
                'message' => 'Correo electrónico de prueba enviado correctamente.',
                'data'    => $emailConfig
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @throws Exception
     */
    public static function sentToSMTP($msg, $mailToSend, $emailConfig): void {
        EmailSendingSMTPJob::dispatch($msg, $mailToSend, $emailConfig);
    }

}
