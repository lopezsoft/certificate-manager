<?php

namespace App\Services\Events;

use App\Common\ReceptionEventNotification;
use App\Models\business\Customer;
use App\Models\Company;
use App\Models\Settings\Software;
use App\Services\Certificate\CertificateValidatorService;
use App\Services\DianResponseService;
use App\Services\Xml\XmlExtractDataService;
use App\Traits\DocumentTrait;
use Illuminate\Support\Str;
use Lopezsoft\UBL21dian\Templates\SOAP\SendEvent;
use Lopezsoft\UBL21dian\XAdES\SignEvent;
use Lopezsoft\VerificationDigit\VerificationDigit;

class EventDeliveryService
{
    use DocumentTrait;

    /**
     * @throws \Exception
     */
    public function send(Object $params): object
    {
        $company            = $params->company;
        $typeEvent          = $params->typeEvent;
        $documentReception  = $params->documentReception;
        $resolution         = $params->resolution;
        $eventMaster        = $params->eventMaster;
        CertificateValidatorService::validateCertificate($company->dni);
        $eventMaster->update(['document_status' => 'PROCESSING', 'date_event' => now()]);
        $eventMaster->refresh();
        $receptionPerson    = $params->receptionPerson;
        $notes              = $params->notes;
        $software           = Software::query()->where('company_id', $company->id)->where('type_id', 1)->first();
        $company->software  = $software;
        $documentNumber     = "{$resolution->prefix}{$eventMaster->event_number}";

        $company->dv        = VerificationDigit::getDigit((int)$company->dni);

        /**
         * Se obtiene el xml del documento
         */
        $xmlObject  = new XmlExtractDataService();
        $xml        = $xmlObject->getXmlByDocumentKey($company, $software, $documentReception->cufe_cude);
        $customer   = $xmlObject->getAccountingSupplierPartyData($xml, $company);
        $customer   = Customer::query()->where('dni', $customer->dni)->first()->toArray();
        $customer   = new Company($customer);


        // Type document
        $typeDocument           = $resolution->type_document;
        $responseTypeDocument   = $documentReception->documentType;

        $date               = date('Y-m-d', strtotime($eventMaster->date_event));
        $time               = date('h:i:s', strtotime($eventMaster->date_event));

        // Create XML
        $documentEvent = $this->createXML(compact(
            'software',
            'documentNumber',
            'company',
            'customer',
            'resolution',
            'typeDocument',
            'date',
            'time',
            'notes',
            'typeEvent',
            'responseTypeDocument',
            'documentReception',
            'receptionPerson'
        ));

        $signEvent = new SignEvent($company->certificate->path, $company->certificate->password);
        $signEvent->softwareID    = $software->identification;
        $signEvent->pin           = $software->pin;

        $sendEvent = new SendEvent($company->certificate->path, $company->certificate->password);

        $sendEvent->To           = $software->url;
        $sendEvent->fileName     = "{$documentNumber}.xml";
        $sendEvent->contentFile  = $this->zipBase64($company, $resolution, $signEvent->sign($documentEvent));

        $response       = $sendEvent->signToSend()->getResponseToObject();
        $response       = DianResponseService::getResponse($response);

        $isValid        = ($response->IsValid === "true");
        $errorMessage   = $response->ErrorMessage;
        if(!$isValid) {
            $settings           = collect($company->settings ?? []);
            $notificationMail   = null;
            foreach ($settings as $setting) {
                if ($setting->setting->key_value === 'NOTIFICATIONEMAIL') {
                    $notificationMail = $setting->value;
                }
            }
            ReceptionEventNotification::send($eventMaster, json_encode($errorMessage), $notificationMail);
            if ($this->isValid($errorMessage, 'Documento procesado anteriormente')) {
                $isValid    = true;
            }
        }
        if(!$isValid && $this->isValid($errorMessage, 'Regla: LGC01')) {
            $isValid = true;
        }
        if(!$isValid && $this->isValid($errorMessage, 'Regla: DC24c')) {
            $isValid = true;
        }
        if(!$isValid && $this->isValid($errorMessage, 'Regla: LGC62')) {
            $isValid = true;
        }

        if($isValid) {
            $response   = (object) ['IsValid' => 'true'];
        }
        $eventMaster->update([
            'event_data'        => json_encode($response, JSON_THROW_ON_ERROR),
            'xml_path'          => null,
            'zip_path'          => $this->pathZIP,
            'document_status'   => $isValid ? 'ACCEPTED' : 'REJECTED',
        ]);

        return $response;
    }

    public function isValid($errorMessage, $contains): bool
    {
        return isset($errorMessage->string) && is_string($errorMessage->string) && Str::contains($errorMessage->string, $contains);
    }
}
