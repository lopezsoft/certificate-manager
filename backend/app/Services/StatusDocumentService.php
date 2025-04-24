<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Modules\Company\CompanyQueries;
use App\Services\Certificate\CertificateValidatorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lopezsoft\UBL21dian\Templates\SOAP\GetNumberingRange;

class StatusDocumentService
{
    public function getNumberingRange(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            $software                           = CompanyQueries::getSoftware($request, $company);
            $GetNumberingRange                  = new GetNumberingRange($company->certificate->path, $company->certificate->password);
            $GetNumberingRange->accountCode     = $company->dni;
            $GetNumberingRange->accountCodeT    = $company->dni;
            $GetNumberingRange->softwareCode    = $software->identification;
            $GetNumberingRange->To              = $software->url;

            $response   = $GetNumberingRange->signToSend()->getResponseToObject();
            return HttpResponseMessages::getResponse([
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => $response,
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
