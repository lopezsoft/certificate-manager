<?php

namespace App\Services\Mail;

use App\Models\SuppressedEmail;
use App\Notifications\BlockedSendAttemptNotification;
use App\Notifications\EmailSuppressedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class EmailSuppressedNotificationService
{
    public static function send($messageData): void
    {
        $emailLog = $messageData->emailLog;
        $MAIL_SUPPORT_ADDRESS = env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co');
        $SEND_MAIL_TO_SUPPORT = env('SEND_MAIL_TO_SUPPORT', false);
        if ($SEND_MAIL_TO_SUPPORT) {
            // Enviar la notificación por correo electrónico al soporte
            Notification::route('mail', $MAIL_SUPPORT_ADDRESS)->notify(new EmailSuppressedNotification($messageData));
        }

        $company    = $emailLog->company;
        $settings   = collect($company->settings ?? []);
        $notificationMail = null;
        foreach ($settings as $setting) {
            if ($setting->setting->key_value === 'NOTIFICATIONEMAIL') {
                $notificationMail = $setting->value;
            }
        }
        if(empty($notificationMail)) {
            $notificationMail = $company->email;
        }
        if(!empty($notificationMail)) {
            Notification::route('mail', $notificationMail)->notify(new EmailSuppressedNotification($messageData));
        }
    }

    /**
     * @throws \Exception
     */
    public static function checkSuppressed($mails, $company, $subject = null): void
    {
        $emailList = explode(',', $mails);
        if (count($emailList) == 1) {
            $emailList = explode(';', $mails);
        }
        Log::info('Lista de correos: ' . json_encode($emailList));
        $recipients = collect($emailList)
            ->filter() // Eliminar posibles nulos/vacíos
            ->unique() // No verificar la misma dirección múltiples veces
            ->values() // Resetear keys del array/colección
            ->all();
        Log::info('Lista de correos filtrada: ' . json_encode($recipients));
        // 2. Consultar la tabla de supresión eficientemente
        $suppressedFound = SuppressedEmail::whereIn('email', $recipients)
            ->pluck('email') // Obtener solo los emails suprimidos encontrados
            ->all();

        Log::info('Lista de correos suprimidos: ' . json_encode($suppressedFound));

        if (!empty($suppressedFound)) {
            // --- INICIO: Notificar a la COMPAÑÍA ---
            $messageData = (object) [
                'originalSubject'   => $subject,
                'suppressedEmails'  => $suppressedFound,
                'company'           => $company,
            ];
            $MAIL_SUPPORT_ADDRESS = env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co');
            $SEND_MAIL_TO_SUPPORT = env('SEND_MAIL_TO_SUPPORT', false);
            if ($SEND_MAIL_TO_SUPPORT) {
                // Enviar la notificación por correo electrónico al soporte
                Notification::route('mail', $MAIL_SUPPORT_ADDRESS)->notify(new BlockedSendAttemptNotification($messageData));
            }
            $settings   = collect($company->settings ?? []);
            $notificationMail = null;
            foreach ($settings as $setting) {
                if ($setting->setting->key_value === 'NOTIFICATIONEMAIL') {
                    $notificationMail = $setting->value;
                }
            }
            if(empty($notificationMail)) {
                $notificationMail = $company->email;
            }
            if(!empty($notificationMail)) {
                Notification::route('mail', $notificationMail)->notify(new BlockedSendAttemptNotification($messageData));
            }

            // --- FIN: Notificar a la COMPAÑÍA ---

            // Aquí puedes lanzar una excepción o manejar el error como desees
            throw new \Exception('El envío fue bloqueado automáticamente porque la(s) siguiente(s) dirección(es)
            de destinatario(s) se encuentra(n) en nuestra lista de supresión (debido a rebotes permanentes o quejas de spam anteriores): ' .
                implode(', ', $suppressedFound), 400);
        }
    }
}
