<?php

namespace App\Jobs\Emails;

use App\Mail\SendInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendInvoiceJob implements ShouldQueue
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
        public Array $data,
        public $table
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if( Str::length($this->mailToSend) > 10 ){
            // Envío del correo al Cliente
            $mail 	= explode(';', $this->mailToSend);
            if(count($mail)  === 1 ) {
                $mail 	= explode(',', $this->mailToSend);
            }
            $send   = new SendInvoice($this->msg);
            // Si el cliente tiene más de 1 correo configurado para el envío.
            if(count($mail) > 1 ) {
                foreach ($mail as $key => $value) {
                    Mail::to($value)->send($send);
                    DB::table($this->table)->insert($this->data);
                }
            }else {
                Mail::to($this->mailToSend)->send($send);
                DB::table($this->table)->insert($this->data);
            }
        }
    }
}
