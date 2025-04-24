<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendInvoice extends Mailable
{
    use Queueable, SerializesModels;

    public $msg;
    public $subject = "Envío de documentos electrónicos";
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        $this->msg      = $message;
        $this->subject  = $message->subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if(strlen($this->msg->file_zip) > 0) {
            return $this->from($this->msg->email_from, $this->msg->voucher_name)
                ->attach($this->msg->file_zip)
                ->view('email.send_invoice');
        }else{
            return $this->from($this->msg->email_from, $this->msg->voucher_name)
                ->view('email.send_invoice');

        }
    }
}
