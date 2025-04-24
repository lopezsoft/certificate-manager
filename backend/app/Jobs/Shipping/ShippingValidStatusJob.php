<?php

namespace App\Jobs\Shipping;

use App\Services\Certificate\CertificateValidatorService;
use App\Services\DianResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lopezsoft\UBL21dian\Templates\SOAP\GetStatus;

class ShippingValidStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $dataRecord
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
            $dataRecord             = $this->dataRecord;
            $company                = $dataRecord->company;
            $isExpired              = CertificateValidatorService::isExpired($company);
            $validValue             = 3;
            if (!$isExpired) {
                $trackId                = $dataRecord->XmlDocumentKey;
                $getStatus              = new GetStatus($company->certificate->path, $company->certificate->password);
                $getStatus->trackId     = $trackId;
                $getStatus->To          = env('UBL_URL_PRODUCTION');
                $response               = $getStatus->signToSend()->getResponseToObject();
                $dianResponse           = DianResponseService::getResponse($response);
                $isValid                = ($dianResponse->IsValid == "true");
                $statusCode             = $dianResponse->StatusCode;
                $errorMessage           = $dianResponse->ErrorMessage;
                if(!$isValid) {
                    DB::table('shipping_data')
                        ->updateOrInsert([
                            'trackid'   => $dataRecord->uuid,
                            'data'      => json_encode($dianResponse),
                        ]);
                    if (isset($errorMessage->string) && is_string($errorMessage->string)) {
                        if (Str::contains($errorMessage->string, 'procesado anteriormente')){
                            $validValue = 1;
                        }
                    }
                    if ($statusCode == '98') { // En proceso
                        $validValue = 0;
                    }
                }
            }
            DB::table('shipping_history')
                ->where('id', $dataRecord->id)
                ->update([
                    'is_valid'  => $validValue,
                    'status'    => $validValue,
                ]);
        }catch (\Exception $e) {
            Log::log('ShippingValidStatusJob Error: ', $e->getMessage());
            throw new \Exception($e);
        }
    }
}
