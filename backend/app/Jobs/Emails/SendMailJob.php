<?php

namespace App\Jobs\Emails;

use App\Mail\SendMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public $msg,
        public $mailToSend,
        public array $data,
        public $table
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            // Envío del correo al Cliente
            $mail     = explode(';', $this->mailToSend);
            if (count($mail)  === 1) {
                $mail     = explode(',', $this->mailToSend);
            }
            $this->msg->mailToSend = $this->mailToSend;
            $send   = new SendMail($this->msg);
            $mailListSending = [];
            foreach ($mail as $value) {
                // Clear data
                $value = trim($value);
                $value = str_replace(' ', '', $value);
                $value = str_replace(';', '', $value);
                $value = str_replace(',', '', $value);
                $value = str_replace('..', '.', $value);
                $mailListSending[] = $value;
            }
            Mail::to($mailListSending)->send($send);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
