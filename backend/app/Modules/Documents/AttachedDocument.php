<?php

namespace App\Modules\Documents;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\StatusProcessor;
use App\Models\Company;
use App\Models\JsonData;
use App\Models\ShippingHistory;
use App\Models\Types\TypeDocument;
use App\Models\User;
use App\Modules\Documents\Invoice\Customer;
use App\Services\AttachedDocumentService;
use App\Services\DianResponseService;
use App\Services\PdfDocumentService;
use App\Traits\DocumentTrait;
use App\Traits\MessagesTrait;
use DOMDocument;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Lopezsoft\UBL21dian\Templates\SOAP\GetStatus;
use Lopezsoft\UBL21dian\XAdES\SignAttachedDocument;
use ZipArchive;

class AttachedDocument implements StatusProcessor
{
    use MessagesTrait, DocumentTrait;

    /**
     * @throws \Exception
     */
    public function process(Request $request, $trackId): JsonResponse
    {
        try {

            $shipping   = ShippingHistory::where('XmlDocumentKey', $trackId)
                            ->first();
            if (!$shipping) {
                throw new Exception('No se encontró un documento asociado al trackId: '. $trackId, 404);
            }
            if ($shipping->is_valid == 0) {
                throw new Exception('El documento no ha sido validado por la DIAN');
            }
            $company    = Company::where('id', $shipping->company_id)->first();
            if (AttachedDocumentService::isExists($shipping)){
                $attached = AttachedDocumentService::extractWhenExists($shipping);
            } else {
                $jsonData       = JsonData::query()->where('shipping_id', $shipping->id)->first();
                if (!$jsonData) {
                    throw new Exception('No es posible generar el AttachedDocument. Inténtelo más tarde', 404);
                }
                $jsonData               = (object) $jsonData->jdata;
                if(!property_exists($jsonData, 'dni') || (isFinalConsumer($jsonData->dni))) {
                    throw new Exception("No es posible generar el documento para un consumidor final.", 404);
                }
                $user                   = User::query()->find($shipping->user_id);
                $XmlBase64              = (new DocumentXml())->getContent($company, $shipping->XmlDocumentKey);
                $document_number        = $shipping->document_number;
                $getStatus              = new GetStatus($company->certificate->path, $company->certificate->password);
                $getStatus->trackId     = $trackId;
                $getStatus->To          = ENV('UBL_URL_PRODUCTION', 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl');
                $response               = $getStatus->signToSend()->getResponseToObject();
                if (property_exists($jsonData->customer, 'company')) {
                    $customer = (array) $jsonData->customer->company;
                    $customer = Customer::getCustomerData($customer);
                } else {
                    $customer   = AttachedDocumentService::getCustomer($jsonData->dni);
                }
                $params                 = (object) [
                    'response'          => $response,
                    'XmlBase64'         => $XmlBase64,
                    'company'           => $company,
                    'document_number'   => $document_number,
                    'customer'          => $customer,
                    'user'              => $user,
                ];
                $attached                   = $this->store($params);
                $shipping->attachedPath     = $attached->path;
                $shipping->attachedZipPath  = $attached->pathZip;
                $shipping->save();
            }
            return HttpResponseMessages::getResponse([
                'attachedDocument'  => $attached,
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @throws Exception
     */
    public function store($params): object | null
    {
        $user               = $params->user;
        // User company
        $user->company      = $params->company;
        $response           = $params->response;
        $company            = $params->company;
        $XmlBase64          = $params->XmlBase64;
        $document_number    = $params->document_number;
        $customer           = $params->customer;
        $dianResponse       = DianResponseService::getResponse($response);

        $response_date      = $response->Envelope->Header->Security->Timestamp->Created;
        $response_date      = date('Y-m-d', strtotime($response_date));
        $cufe               = $dianResponse->XmlDocumentKey;

        $app_response       = base64_decode($dianResponse->XmlBase64Bytes);

        $xmlResponse = new DOMDocument();
        $xmlResponse->loadXML($app_response);

        $xml_file   = $this->xmlToObject($xmlResponse);

        $validation_date    = $xml_file->ApplicationResponse->cbIssueDate;
        $validation_time    = $xml_file->ApplicationResponse->cbIssueTime;

        $validation_code    = $xml_file->ApplicationResponse->caDocumentResponse->caResponse->cbResponseCode;

        $xml                = base64_decode($XmlBase64);
        // Create XML
        $invoice        = $this->createDocumentXML(compact('user', 'company', 'customer', 'document_number', 'app_response', 'cufe', 'response_date', 'validation_code', 'validation_date', 'validation_time', 'xml'), 'attachedDocument');
        $signAttached   = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
        $typeDocument   = TypeDocument::find(99);
        if (!$typeDocument) {
            $typeDocument = TypeDocument::create([
                'id'            => 99,
                'code'          => '999',
                'voucher_name'  => 'Attached document',
                'cufe_algorithm'=> 'CUDS-SHA384',
                'prefix'        => 'ad',
                'active'        => 1,
            ]);
        }
        $this->saveDocument($company, $signAttached->sign($invoice), $typeDocument);

        $attachedDocument   = $this->getAttachmentXML();
        $path               = $this->getAttachmentPathXML();
        $pathZip            = $this->getAttachmentPathZIP();

        return (object)[
            'pathZip'   => $pathZip,
            'path'      => $path,
            'url'       => Storage::disk('attachment')->url($path),
            'data'      => $attachedDocument
        ];
    }

    /**
     * @throws Exception
     */
    public static function storeZip($shipping): object
    {
        try {
            $attachmentPathZIP  = null;
            // Se comprimen los archivos, el AttachedDocument y la representación gráfica
            if(PdfDocumentService::isExists($shipping) && AttachedDocumentService::isExists($shipping))
            {
                $documentName       = str_replace('.xml', '', $shipping->XmlDocumentName);
                $attachmentPathZIP  = Storage::disk('attachment')->path($shipping->attachedZipPath);
                $attachmentPathZIP  = str_replace('/', DIRECTORY_SEPARATOR, $attachmentPathZIP);
                $pdf                = ContentDocument::getPdfContent($shipping);
                $zip = new ZipArchive();
                $zip->open($attachmentPathZIP, ZipArchive::CREATE);
                $zip->addFromString( "{$documentName}.pdf", base64_decode($pdf->data));
                $zip->close();
            }
            return (Object) [
                'zip' => $attachmentPathZIP,
            ];
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

}
