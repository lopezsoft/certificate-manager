<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Interfaces\StatusProcessor;
use App\Models\ShippingHistory;
use App\Modules\Company\CompanyQueries;
use App\Services\Certificate\CertificateValidatorService;
use App\Services\DianResponseService;
use Exception;
use Illuminate\Http\Request;
use Lopezsoft\UBL21dian\Templates\SOAP\GetStatus;

class StatusDocument implements StatusProcessor
{
    /**
     * @throws \Exception
     */
    public function process(Request $request, $trackId): \Illuminate\Http\JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            $data       = ShippingHistory::where('XmlDocumentKey', $trackId)
                            ->where('company_id', $company->id)
                            ->first();
            $request->company       = $company;
            $request->type_id       = TypeDocumentIdSoftware::getId($data->type_document_id  ?? 7);
            $software               = CompanyQueries::getSoftware($request, $company);
            $request->software      = $software;
            $getStatus              = new GetStatus($company->certificate->path, $company->certificate->password);
            $getStatus->trackId     = $trackId;
            $getStatus->To          = env('UBL_URL_PRODUCTION');
            $response               = $getStatus->signToSend()->getResponseToObject();
            return HttpResponseMessages::getResponse([
                'message'   => 'Consulta generada con éxito',
                'response'  => DianResponseService::getResponse($response),
            ]);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
