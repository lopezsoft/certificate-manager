<?php

namespace App\Services\Events;

use App\Common\DateFunctions;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Enums\DocumentStatusEnum;
use App\Models\Events\DocumentReception;
use App\Models\Events\EventMaster;
use App\Models\Events\TypeEvent;
use App\Models\Settings\Software;
use App\Modules\Company\CompanyQueries;
use App\Services\Xml\XmlExtractDataService;
use App\Validators\EventMasterValidator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventMasterService
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
    public function create(Request $request, $trackId): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $code       = $request->input('code');
            $notes      = $request->input('notes');
            $validator  = new EventMasterValidator();
            $typeEvent  = TypeEvent::query()->where('code', $code)->first();
            if (!$typeEvent) {
                throw new Exception('El tipo de evento no existe', 400);
            }

            $documentReception  = DocumentReception::query()->where('cufe_cude', $trackId)->first();
            if (!$documentReception) {
                $this->createDocument($company, $trackId);
                $documentReception  = DocumentReception::query()->where('cufe_cude', $trackId)->first();
            }
            if ($documentReception->payment_method_id == 1) {
                throw new Exception('No se puede generar un evento para un documento con medio de pago de contado.', 400);
            }
            $resolution         = $validator->resolution($company);
            $receptionPerson    = $validator->receptionPerson($company);

            $eventMaster = $this->getEventMasterQuery($company, $resolution)
                ->where('document_reception_id', $documentReception->id)
                ->where('type_event_id', $typeEvent->id)
                ->first();
            $eventSend  = new EventDeliveryService();
            $params     = (object) [
                'company'           => $company,
                'typeEvent'         => $typeEvent,
                'documentReception' => $documentReception,
                'resolution'        => $resolution,
                'eventMaster'       => $eventMaster,
                'receptionPerson'   => $receptionPerson,
                'notes'             => $notes,
            ];
            if ($eventMaster) {
                if ($eventMaster->document_status == DocumentStatusEnum::getAccepted()) {
                    throw new Exception('El evento ya fue validado y aceptado.', 400);
                }
                $response = $eventSend->send($params);
                return HttpResponseMessages::getResponse([
                    'message' => 'Evento enviado con éxito',
                    'data'    => $response
                ]);
            }

            $documentNumber     = $this->nextEventNumber($company, $resolution);

            $eventMaster    = EventMaster::create([
                'company_id'            => $company->id,
                'resolution_id'         => $resolution->id,
                'document_reception_id' => $documentReception->id,
                'type_event_id'         => $typeEvent->id,
                'event_number'          => $documentNumber,
                'date_event'            => now(),
                'description'           => $notes,
            ]);
            $params->eventMaster = $eventMaster;
            $response   = $eventSend->send($params);
            return HttpResponseMessages::getResponse([
                'message' => 'Evento creado con éxito',
                'data'    => $response
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
    public function nextEventNumber($company, $resolution): int
    {
        $eventMaster        = $this->getEventMasterQuery($company, $resolution)
            ->orderBy('event_number', 'desc')
            ->first();
        $documentNumber     = $resolution->initial_number;
        if ($eventMaster) {
            $documentNumber = $eventMaster->event_number + 1;
        }
        return $documentNumber;
    }

    /**
     * @throws Exception
     */
    public function createDocument($company, $trackId) {
        $software   = Software::query()
            ->where('company_id', $company->id)
            ->where('type_id', 1)
            ->first();
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
        return DocumentReception::create($data);
    }
    private function getEventMasterQuery($company, $resolution): \Illuminate\Database\Eloquent\Builder
    {
        return EventMaster::query()
            ->where('company_id', $company->id)
            ->where('resolution_id', $resolution->id);
    }

}
