<?php

namespace App\Modules\Documents\Payroll;

use App\Common\VerificationDigit;
use App\Models\Ep\AdjustmentNoteType;
use App\Models\Language;
use App\Models\Types\TypeCurrency;
use App\Modules\Documents\Payroll\Ep\Deductions;
use App\Modules\Documents\Payroll\Ep\Earn;
use App\Traits\ElectronicDocumentsTrait;
use Exception;
use Lopezsoft\UBL21dian\Templates\SOAP\SendBillAsync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendNominaSync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendTestSetAsync;
use Lopezsoft\UBL21dian\XAdES\SignPayroll;
use Lopezsoft\UBL21dian\XAdES\SignPayrollAdjustment;

class PayrollProcessor
{
    use ElectronicDocumentsTrait;

    /**
     * @throws Exception
     */
    public function process($request): object
    {
        try {
            $resolution         = $request->resolution;
            $company            = $request->company;
            $user               = $request->user;
            $software           = $request->software;
            $language           = $request->language_id         ?? 842;
            $type_document_id   = $request->type_document_id    ?? 13;
            $notes              = $request->notes               ?? null;
            $async              = $request->async               ?? false;
            $worker_code        = $request->worker_code         ?? null;
            $novelty            = $request->novelty             ?? false;
            $document_number    = $this->getDocumentNumber($request, $resolution);
            $sequenceNumber     =  PayrollSequenceNumber::get($request, $resolution);
            // Lugar de generación del XML
            $generationPlace    = PayrollGenerationPlace::get($request);
            $company->dv        = VerificationDigit::getDigit(intval($company->dni));
            // Type document
            $language           = Language::findOrFail($language);
            /**
             * Periodo de la nómina
             */
            $period             = PayrollPeriod::get($request);
            if(!$period) {
                throw new Exception('El periodo de la nómina no es correcto.', 400);
            }
            /**
             * Información general de la nómina
             */
            $generalInformation     = PayrollGeneralInformation::get($request);
            if(!$generalInformation) {
                throw new Exception('La información general de la nómina no es correcto.', 400);
            }
            /**
             * Información del empleado
             */
            $employee           = PayrollEmployee::get($request);
            if(!$employee) {
                throw new Exception('La información del empleado no es correcta.', 400);
            }
            /**
             * Información del predecesor
             */
            $replacingPredecessor   = null;
            $adjustmentNoteType     = null;
            if($type_document_id === 14) {
                $adjustmentNoteType     = AdjustmentNoteType::findOrFail(1);
                $replacingPredecessor   =  PayrollReplacingPredecessor::get($request);
                if (!$replacingPredecessor) {
                    throw new Exception('La información del predecesor está errada.', 400);
                }
            }

            // Type document
            $typeDocument       = $resolution->type_document;
            // Date time
            $date               = $request->pay_day ?? $this->getDate($request);
            $time               = $this->getTime($request);
            // Payment form
            $paymentForm        = PayrollPayment::get($request);
            // currency
            $currency           = TypeCurrency::findOrFail($paymentForm->currency_id ?? 272);
            // Devengado
            $earn               = Earn::getEarn($request);
            if(!$earn) {
                throw new Exception('La información del devengado no es correcta.', 400);
            }
            // deductions
            $deductions         = Deductions::getDeductions($request);
            if(!$deductions) {
                throw new Exception('La información de las deducciones no es correcta.', 400);
            }
            $rounding           = null;
            if(isset($request->rounding)){
                $rounding           = $request->rounding;
            }
            $total_earned       = number_format($request->total_earned, 2, '.', '')   ?? 0.00;
            $deductions_total   = number_format($request->deductiones_total ?? $request->deductions_total, 2, '.', '') ?? 0.00;
            $total_voucher      = number_format($request->total_voucher, 2, '.', '') ?? 0.00;

            $stringCune         =   $sequenceNumber->number.$generalInformation->generation_date.$generalInformation->generation_time."-05:00".
                $total_earned.$deductions_total.$total_voucher.$company->dni.$employee->document_number.
                $typeDocument->code.$software->pin.$software->environment->code;
            $cune               = hash('sha384', $stringCune);

            // Create XML
            $invoice = $this->createXML(compact(
                'user',
                'company',
                'adjustmentNoteType',
                'replacingPredecessor',
                'employee',
                'resolution',
                'period',
                'paymentForm',
                'notes',
                'typeDocument',
                'earn',
                'deductions',
                'date',
                'time',
                'sequenceNumber',
                'generalInformation',
                'currency',
                'language',
                'generationPlace',
                'software',
                'cune',
                'rounding',
                'total_earned',
                'deductions_total',
                'total_voucher'
            ));

            // Signature XML
            if($type_document_id === 14) { // Nota de ajuste, reemplazo
                $signPayroll = new SignPayrollAdjustment($company->certificate->path, $company->certificate->password);
            }else {
                $signPayroll = new SignPayroll($company->certificate->path, $company->certificate->password);
            }

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

            $XmlBase64  = $this->getXML();
            $XmlName    = $this->getNameXML();
            $xmlPath    = $this->getPathXML();
            $zipPath    = $this->getPathZip();
            $response   = $sendBill->signToSend()->getResponseToObject();
            if ($software->environment->code === 1) { // Production
                if ($async) {
                    $zipKey         = $response->Envelope->Body->SendBillAsyncResponse->SendBillAsyncResult->ZipKey;
                } else {
                    $dianResponse   = $response->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult;
                    $zipKey         = $dianResponse->XmlDocumentKey;
                }
            } else {
                $zipKey         = $response->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey;
            }

            //Guarda la información del documento para la representación gráfica.
            $jsonData   = json_encode([
                'replacingPredecessor' => $replacingPredecessor,
                'employee' => $employee,
                'period' => $period,
                'paymentForm' => $paymentForm,
                'notes' => $notes,
                'typeDocument' => $typeDocument,
                'earn' => $earn,
                'deductions' => $deductions,
                'date' => $date,
                'time' => $time,
                'sequenceNumber' => $sequenceNumber,
                'generalInformation' => $generalInformation,
                'currencyId' => $currency->id,
                "languageId" => $language->id,
                'generationPlace' => $generationPlace,
                'cune' => $cune,
                'rounding' => $rounding,
                'total_earned' => $total_earned,
                'deductions_total' => $deductions_total,
                'total_voucher' => $total_voucher,
            ], JSON_THROW_ON_ERROR);

            return (Object) [
                'XmlBase64'         => $XmlBase64,
                'XmlDocumentName'   => $XmlName,
                'ZipKey'            => $zipKey,
                'ShippingMethod'    => $ShippingMethod,
                'dianResponse'      => $response,
                'jsonData'          => $jsonData,
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
