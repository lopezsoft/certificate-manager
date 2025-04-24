<?php

namespace App\Services\Events;

use App\Common\DateFunctions;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Imports\DocumentReceptionsImport;
use App\Models\Events\DocumentReception;
use App\Models\Settings\Software;
use App\Modules\Company\CompanyQueries;
use App\Services\Certificate\CertificateValidatorService;
use App\Services\DianResponseService;
use App\Services\Xml\XmlExtractDataService;
use App\Validators\EventMasterValidator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lopezsoft\UBL21dian\Templates\SOAP\GetStatusEvents;
use Maatwebsite\Excel\Facades\Excel;

class DocumentReceptionService
{
    private array $documentId = [
        '92' => 4,
        '91' => 5,
        '01' => 7,
    ];

    private array $documentTypeList = [
        '92',
        '91',
        '01'
    ];
    public function importExcel(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $validator  = new EventMasterValidator();
            // Validaciones
            $resolution  = $validator->resolution($company);
            $validator->receptionPerson($company);
            // Importar
            $import     = new DocumentReceptionsImport($company, $resolution);
            $file       = $request->file('file');
            if (!$file) {
                throw new Exception('No ha enviado un archivo para importar', 400);
            }
            Excel::import($import, $file);
            return HttpResponseMessages::getResponse([
                'message' => 'Importación exitosa'
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getEventStatus($trackId): JsonResponse
    {
        try {
            $company            = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            $software           = DB::table('software_information')->where('company_id', $company->id)->first();
            $GetStatus          = new GetStatusEvents($company->certificate->path, $company->certificate->password);
            $GetStatus->trackId = $trackId;
            $GetStatus->To      = $software->url;
            $response           = $GetStatus->signToSend()->getResponseToObject();
            return HttpResponseMessages::getResponse([
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => DianResponseService::getResponse($response),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
    public function getDocumentReceptions(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $search     = $request->input('query');
            $startDate  = $request->input('startDate');
            $endDate    = $request->input('endDate');
            $trackId    = $request->input('trackId');
            $limit      = $request->input('limit') ?? 20;
            if ($limit > 50) {
                $limit = 50;
            }
            $documentReceptions = DocumentReception::query()
                ->where('company_id', $company->id)
                ->orderBy('issue_date', 'desc')
                ->with(['paymentMethod', 'people', 'events']);
            if ($trackId) {
                $documentReceptions->where('cufe_cude', $trackId);
            } else {
                if ($search) {
                    $documentReceptions->where('issuer_name', 'like', "%$search%")
                        ->orWhere('issuer_nit', 'like', "%$search%");
                }

                if ($startDate) {
                    $documentReceptions->where('created_at', '>=', DateFunctions::transformDate($startDate));
                }

                if ($endDate) {
                    $documentReceptions->where('created_at', '<=', DateFunctions::transformDate($endDate));
                }
            }


            $documentReceptions->where('company_id', $company->id);
            return HttpResponseMessages::getResponse([
                'dataRecords'   => $documentReceptions->paginate($limit),
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getEventSById(Request $request, $documentId): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $events     = DocumentReception::query()
                ->where('company_id', $company->id)
                ->where('id', $documentId)
                ->with(['events', 'people']);
            return HttpResponseMessages::getResponse([
                'dataRecords' => $events->paginate(),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function importTrackId(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $software   = Software::query()
                            ->where('company_id', $company->id)
                            ->where('type_id', 1)
                            ->first();
            $validator  = new EventMasterValidator();
            $resolution = $validator->resolution($company);
            $validator->receptionPerson($company);
            $trackId    = $request->input('trackId');
            if (!$trackId) {
                throw new Exception('No ha enviado un trackId', 400);
            }
            $table  = DB::table('document_receptions')
                ->where('company_id', $company->id)
                ->where('cufe_cude', $trackId)
                ->first();
            if ($table) {
                throw new Exception('El cufe/cude ya ha sido importado', 400);
            }
            $xmlObject  = new XmlExtractDataService();
            $xml        = $xmlObject->getXmlByDocumentKey($company, $software, $trackId);
            $people     = $xmlObject->getAccountingSupplierPartyData($xml, $company);
            $basicInvoiceData   = $xmlObject->getBasicInvoiceData($xml);
            $legalMonetaryTotal = $xmlObject->getLegalMonetaryTotalData($xml);

            if(!in_array($basicInvoiceData->InvoiceTypeCode, $this->documentTypeList, true)) {
                throw new Exception('El tipo de documento no es permitido', 400);
            }
            $documentId             = $this->documentId[$basicInvoiceData->InvoiceTypeCode];
            $paymentId              = $xmlObject->getPaymentMeansData($xml);
            if ($paymentId === 1) {
                throw new Exception('No se puede generar un evento para un documento con medio de pago de contado.', 400);
            }
            $data   =[
                'company_id'        => $company->id,
                'people_id'         => $people->id,
                'document_type_id'  => $documentId,
                'payment_method_id' => $paymentId,
                'cufe_cude'         => $trackId,
                'folio'             => $basicInvoiceData->ID,
                'issue_date'        => DateFunctions::transformDate($basicInvoiceData->IssueDate),
                'total'             => $legalMonetaryTotal->PayableAmount,
                'document_origin'   => 'IMPORTED',
            ];
            $documentReception  = DocumentReception::create($data);
            EventDispatchService::dispatch($company, $documentReception, $resolution);
            return HttpResponseMessages::getResponse([
                'message' => 'Importación exitosa',
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

}
