<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\StatusProcessor;
use App\Modules\Company\CompanyQueries;
use App\Services\Certificate\CertificateValidatorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lopezsoft\UBL21dian\Templates\SOAP\GetStatusZip;

class StatusZip implements StatusProcessor
{
    public function process(Request $request, $trackId): JsonResponse
    {
        try{
            $company    = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            $response       = $this->dianResponse($trackId, $company);
            $dianResponse   = $response->Envelope->Body->GetStatusZipResponse->GetStatusZipResult;
            return HttpResponseMessages::getResponse([
                'message'       => 'Consulta generada con éxito',
                'ResponseDian'  => $dianResponse->DianResponse,
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function dianResponse($trackId, $company): object|array
    {
        $getStatusZip           = new GetStatusZip($company->certificate->path, $company->certificate->password);
        $getStatusZip->trackId  = $trackId;

        return $getStatusZip->signToSend()->getResponseToObject();
    }

}
