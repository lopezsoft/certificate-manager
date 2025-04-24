<?php

namespace App\Jobs\Sales;

use App\Modules\ElectronicDocuments\SendingInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmailSendingSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $params
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
        $params = (object)[
            'sale'              => $this->params->sale,
            'user'              => $this->params->user,
            'company'           => $this->params->company
        ];
        (new SendingInvoice())->sendMail($params);
    }
}
