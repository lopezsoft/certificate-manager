<?php

namespace App\Modules\Documents;

use App\Mail\SendInvoiceByMail;
use App\Models\EmailSending;
use Exception;
use Illuminate\Support\Facades\Mail;

class SendEmail
{

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public static function send($msg, $mailToSend, array $data): void
    {
        try {
            // Envío del correo al Cliente
            $mailList     = explode(';', $mailToSend);
            if (count($mailList)  === 1) {
                $mailList     = explode(',', $mailToSend);
            }
            $msg->mailToSend = $mailToSend;
            $send   = new SendInvoiceByMail($msg);
            $mailListSending = [];
            foreach ($mailList as $value) {
                // Clear data
                $value = trim($value);
                $value = str_replace(' ', '', $value);
                $value = str_replace(';', '', $value);
                $value = str_replace(',', '', $value);
                $value = str_replace('..', '.', $value);
                $mailListSending[] = $value;
            }
            Mail::to($mailListSending)->queue($send);
            foreach ($mailListSending as $value) {
                $data['email_to']   = $value;
                EmailSending::create($data);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
