<?php

namespace App\Common;

use App\Models\Company;
use App\Notifications\ImageFailedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ImageNotification
{
    public static function empty(Company $company, $email): string
    {
        $logo               = str_replace('/storage/', '', $company->image);
        $storage            = storage::disk('public');
        if(!$storage->exists($logo)){
            $mailToSend = $email ?? $company->email;
            $mailList 	= explode(';', $mailToSend);
            if(count($mailList)  === 1 ) {
                $mailList 	= explode(',', $mailToSend);
            }

            foreach ($mailList as $mail) {
                $mail = trim($mail);
                Notification::route('mail',$mail)->notify(new ImageFailedNotification($company));
            }
            $MAIL_SUPPORT_ADDRESS = env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co');
            $SEND_MAIL_TO_SUPPORT = env('SEND_MAIL_TO_SUPPORT', false);
            if($SEND_MAIL_TO_SUPPORT) {
                Notification::route('mail', $MAIL_SUPPORT_ADDRESS)->notify(new ImageFailedNotification($company));
            }
        }
        return storage::disk('public')->url($logo);
    }
}
