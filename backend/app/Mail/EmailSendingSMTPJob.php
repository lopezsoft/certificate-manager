<?php

namespace App\Jobs\Mail;

use App\Mail\SendInvoiceByMail;
use App\Services\Mail\EmailSettingService;
use App\Services\Mail\SMTPConfig;
use App\Services\Mail\SMTPMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmailSendingSMTPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
       public $msg,
       public $mailToSend,
       public $emailConfig
    )
    {
        //
    }

    /**
     * Execute the job.
     * @throws \Exception
     */
    public function handle(): void
    {
        $emailConfig = $this->emailConfig;
        $mailToSend = $this->mailToSend;
        $msg        = $this->msg;
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
        $mails 	= explode(';',$mailToSend);
        if(count($mails)  === 1 ) {
            $mails 	= explode(',',$mailToSend);
        }
        foreach ($mails as $mail) {
            $email = EmailSettingService::sanitizeEmailAddress($mail);
            $symfonyMailer->sendMailable(new SendInvoiceByMail($msg), $email);
        }
    }
}
