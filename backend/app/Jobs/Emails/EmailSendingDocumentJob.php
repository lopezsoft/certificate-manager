<?php

namespace App\Jobs\Emails;

use App\Modules\Documents\TypeDocumentIdSoftware;
use App\Services\EmailSendingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmailSendingDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $documentEmail,
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
        try {
            $documentEmail  = $this->documentEmail;
            $company        = $documentEmail->company;
            $shipping       = $documentEmail->document;
            $emailTo        = $documentEmail->email_to;
            // Crear Http request
            $request        = new \Illuminate\Http\Request();
            $request->merge([
                'type_id'   => TypeDocumentIdSoftware::getId($shipping->type_document_id ?? 7)
            ]);
            // Se actualiza el estado del envío de correo electrónico
            EmailSendingService::send((object)[
                'company'   => $company,
                'request'   => $request,
                'shipping'  => $shipping,
                'emailTo'   => $emailTo,
                'emailData' => $documentEmail
            ]);
        }catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
}
