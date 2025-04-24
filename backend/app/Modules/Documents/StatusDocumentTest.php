<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\StatusProcessor;
use App\Modules\Company\CompanyQueries;
use App\Services\Certificate\CertificateValidatorService;
use App\Traits\MessagesTrait;
use Exception;
use Illuminate\Http\Request;
use Lopezsoft\UBL21dian\Templates\SOAP\GetStatus;

class StatusDocumentTest implements StatusProcessor
{
    use MessagesTrait;
    public function process(Request $request, $trackId): \Illuminate\Http\JsonResponse
    {
        try {
            $company                = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            $getStatus              = new GetStatus($company->certificate->path, $company->certificate->password);
            $getStatus->trackId     = $trackId;
            $response               = $getStatus->signToSend()->getResponseToObject();
            $dianResponse           = $response->Envelope->Body->GetStatusResponse->GetStatusResult;
            return HttpResponseMessages::getResponse([
                'message'       => 'Consulta generada con éxito',
                'ResponseDian'  => $dianResponse,
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
