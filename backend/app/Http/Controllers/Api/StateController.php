<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Company\CompanyQueries;
use App\Modules\Documents\RunStatusProcessor;
use App\Modules\Documents\StatusDocument;
use App\Modules\Documents\StatusDocumentTest;
use App\Modules\Documents\StatusZip;
use App\Services\ShippingQueryService;
use App\Services\StatusDocumentService;
use App\Traits\DocumentTrait;
use App\Traits\ElectronicDocumentsTrait;
use App\Traits\MessagesTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lopezsoft\UBL21dian\Templates\SOAP\GetExchangeEmails;

class StateController extends Controller
{
    use DocumentTrait, ElectronicDocumentsTrait, MessagesTrait;

    public function getExchangeEmails(Request $request): object
    {
        $company                = CompanyQueries::getCompany();
        $software               = CompanyQueries::getSoftware($request, $company);

        $GetExchangeEmails      = new GetExchangeEmails($company->certificate->path, $company->certificate->password);
        $GetExchangeEmails->To  = $software->url;
        $response               = $GetExchangeEmails->signToSend()->getResponseToObject();
        $dianRespon             = $response->Envelope->Body->GetExchangeEmailsResponse->GetExchangeEmailsResult;
        return (object) [
            'message'           => 'Consulta generada con éxito',
            'exchangeEmailData' => $dianRespon->CsvBase64Bytes
        ];
    }

    public function range(Request $request): JsonResponse
    {
        return (new StatusDocumentService())->getNumberingRange($request);
    }

    public function zip($trackId, Request $request)
    {
        return RunStatusProcessor::execute($request, $trackId, new StatusZip());
    }

    public function documentTest($trackId, Request $request)
    {
        return RunStatusProcessor::execute($request, $trackId, new StatusDocumentTest());
    }
    public function document($trackId, Request $request)
    {
        return RunStatusProcessor::execute($request, $trackId, new StatusDocument());
    }

    public function documentStatus(Request $request): JsonResponse
    {
        return ShippingQueryService::getDocumentStatus($request);
    }
}
