<?php

namespace App\Modules\Documents\Payroll;

use App\Common\VerificationDigit;
use App\Models\Ep\AdjustmentNoteType;
use App\Models\Language;
use App\Modules\Documents\JsonProcessor;
use App\Traits\ElectronicDocumentsTrait;
use Exception;
use Lopezsoft\UBL21dian\Templates\SOAP\SendBillAsync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendNominaSync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendTestSetAsync;
use Lopezsoft\UBL21dian\XAdES\SignPayrollAdjustment;

class PayrollDeleteProcessor
{
    use ElectronicDocumentsTrait;

    /**
     * @throws Exception
     */
    public function process($request): object
    {
        try {
            $resolution             = $request->resolution;
            $company                = $request->company;
            $user                   = $request->user;
            $software               = $request->software;
            $language               = $request->language_id         ?? 842;
            $notes                  = $request->notes               ?? null;
            $async                  = $request->async               ?? false;
            $adjustmentNoteType     = AdjustmentNoteType::findOrFail(2);
            $document_number        = $this->getDocumentNumber($request, $resolution);
            $sequenceNumber         =  PayrollSequenceNumber::get($request, $resolution);
            $deletingPredecessor    =  PayrollDeletingPredecessor::get($request);
            if (!$deletingPredecessor) {
                throw new Exception('La información del predecesor está errada.', 400);
            }
            // Lugar de generación del XML
            $generationPlace    = PayrollGenerationPlace::get($request);
            $company->dv        = VerificationDigit::getDigit(intval($company->dni));
            // Lenguaje
            $language           = Language::findOrFail($language);
            /**
             * Información general de la nómina
             */
            $generalInformation     = PayrollGeneralInformation::get($request);
            if(!$generalInformation) {
                throw new Exception('La información general de la nómina no es correcto.', 400);
            }

            // Type document
            $typeDocument       = $resolution->type_document;

            $total_earned       = '0.00';
            $deductions_total   = '0.00';
            $total_voucher      = '0.00';

            $stringCune         =   $sequenceNumber->number.$generalInformation->generation_date.$generalInformation->generation_time."-05:00".
                $total_earned.$deductions_total.$total_voucher.$company->dni."0".
                $typeDocument->code.$software->pin.$software->environment->code;
            $cune               = hash('sha384', $stringCune);
            // Create XML
            $invoice = $this->createXML(compact(
                'user',
                'adjustmentNoteType',
                'deletingPredecessor',
                'company',
                'resolution',
                'notes',
                'typeDocument',
                'sequenceNumber',
                'generalInformation',
                'generationPlace',
                'language',
                'software',
                'cune',
            ));
            // Signature XML
            $signPayroll                = new SignPayrollAdjustment($company->certificate->path, $company->certificate->password);
            $signPayroll->softwareID    = $software->identification;
            $signPayroll->pin           = $software->pin;

            if ($software->environment->code == 1) {  // Production
                if ($async) {
                    $sendBill               = new SendBillASync($company->certificate->path, $company->certificate->password);
                    $ShippingMethod         = 'SendBillASync';
                } else {
                    $sendBill               = new SendNominaSync($company->certificate->path, $company->certificate->password);
                    $ShippingMethod         = 'SendNominaSync';
                }
            } else {
                $ShippingMethod         = 'SendTestSetAsync';
                $sendBill               = new SendTestSetAsync($company->certificate->path, $company->certificate->password);
                $sendBill->testSetId    = $software->testsetid;
            }
            $sendBill->To           = $software->url;
            $sendBill->fileName     = "{$document_number}.xml";
            $sendBill->contentFile  = $this->zipBase64($company, $resolution, $signPayroll->sign($invoice));

            $response           = $sendBill->signToSend()->getResponseToObject();
            $XmlBase64          = $this->getXML();
            $XmlName            = $this->getNameXML();
            $xmlPath            = $this->getPathXML();
            $zipPath            = $this->getPathZip();
            if ($software->environment->code == 1) { // Production
                if ($async) {
                    $zipKey         = $response->Envelope->Body->SendBillAsyncResponse->SendBillAsyncResult->ZipKey;
                } else {
                    $dianResponse   = $response->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult;
                    $zipKey         = $dianResponse->XmlDocumentKey;
                }
            } else {
                $zipKey     = $response->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey;
            }

            //Guarda la información del documento para la representación gráfica.
            $jsonData   = json_encode([
                'user'                  => $user,
                'adjustmentNoteType'    => $adjustmentNoteType,
                'deletingPredecessor'   => $deletingPredecessor,
                'company'               => $company,
                'notes'                 => $notes,
                'language'              => $language,
                'typeDocument'          => $typeDocument,
                'sequenceNumber'        => $sequenceNumber,
                'generalInformation'    => $generalInformation,
                'generationPlace'       => $generationPlace,
                'software'              => $software,
                'cune'                  => $cune
            ]);
            $jsonPath   = JsonProcessor::storeData($XmlName, $company, $jsonData);
            return (Object) [
                'XmlBase64'         => $XmlBase64,
                'XmlDocumentName'   => $XmlName,
                'ZipKey'            => $zipKey,
                'ShippingMethod'    => $ShippingMethod,
                'dianResponse'      => $response,
                'jsonData'          => $jsonData,
                'jsonPath'          => $jsonPath,
                'document_number'   => $document_number,
                'XmlDocumentKey'    => $cune,
                'xmlPath'           => $xmlPath,
                'zipPath'           => $zipPath
            ];
        }catch (Exception $e) {
            throw new Exception($e->getMessage(), 400);
        }
    }
}
