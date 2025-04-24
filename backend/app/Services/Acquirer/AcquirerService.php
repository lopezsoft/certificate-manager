<?php

namespace App\Services\Acquirer;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\Request;
use Lopezsoft\UBL21dian\Templates\SOAP\GetAcquirer;

class AcquirerService
{
    public static function getAcquirer(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'identificationType' => 'required|numeric',
                'identificationNumber' => 'required|string',
            ], [
                'identificationType.required' => 'El tipo de identificación es requerido',
                'identificationType.numeric' => 'El tipo de identificación debe ser un número',
                'identificationNumber.required' => 'El número de identificación es requerido',
                'identificationNumber.string' => 'El número de identificación debe ser una cadena de texto',
            ]);
            $company                        = CompanyQueries::getCompany();
            $identificationType             = $request->input('identificationType');
            $identificationNumber           = $request->input('identificationNumber');

            $acquirer            = new GetAcquirer($company->certificate->path, $company->certificate->password);
            $acquirer->identificationType   = $identificationType;
            $acquirer->identificationNumber = $identificationNumber;
            $acquirer->To        = ENV('UBL_URL_PRODUCTION', 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl');
            $response                       = $acquirer->signToSend()->getResponseToObject();
            return HttpResponseMessages::getResponse([
                'message'   => 'Consulta generada con éxito',
                'content'   => $response->Envelope->Body->GetAcquirerResponse->GetAcquirerResult
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
