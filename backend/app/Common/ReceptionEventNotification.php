<?php

namespace App\Common;

use App\Models\Events\EventMaster;
use App\Notifications\ReceptionEventFailedNotification;
use Illuminate\Support\Facades\Notification;

class ReceptionEventNotification
{
    public static function send(EventMaster $event, $error, $email): void
    {
        if(!empty($email)){
            $mailList 	= explode(';', $email);
            if(count($mailList)  === 1 ) {
                $mailList 	= explode(',', $email);
            }

            foreach ($mailList as $mail) {
                $mail = trim($mail);
                Notification::route('mail',$mail)->notify(new ReceptionEventFailedNotification($event, $error));
            }
        }
        $MAIL_SUPPORT_ADDRESS = env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co');
        $SEND_MAIL_TO_SUPPORT = env('SEND_MAIL_TO_SUPPORT', false);
        if($SEND_MAIL_TO_SUPPORT) {
            Notification::route('mail', $SEND_MAIL_TO_SUPPORT)->notify(new ReceptionEventFailedNotification($event, $error));
        }
    }
}
