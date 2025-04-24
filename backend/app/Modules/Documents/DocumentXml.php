<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\DocumentsInterface;
use App\Modules\Company\CompanyQueries;
use App\Services\Certificate\CertificateValidatorService;
use Exception;
use Illuminate\Http\Request;
use Lopezsoft\UBL21dian\Templates\SOAP\GetXmlByDocumentKey;

class DocumentXml Implements DocumentsInterface
{
    public function process(Request $request, $trackId): object
    {
        try {
            $company    = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            return HttpResponseMessages::getResponse([
                'message'   => 'Consulta generada con éxito',
                'content'   => $this->getContent($company, $trackId)
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
    public function getContent($company, $trackId) {
        $GetXmlByDocumentKey            = new GetXmlByDocumentKey($company->certificate->path, $company->certificate->password);
        $GetXmlByDocumentKey->trackId   = $trackId;
        $GetXmlByDocumentKey->To        = ENV('UBL_URL_PRODUCTION', 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl');
        $response                       = $GetXmlByDocumentKey->signToSend()->getResponseToObject();
        return $response->Envelope->Body->GetXmlByDocumentKeyResponse->GetXmlByDocumentKeyResult;
    }
}
