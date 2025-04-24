<?php

namespace App\Modules\Documents\Invoice;

use App\Common\VerificationDigit;
use App\Models\Language;
use App\Models\Types\TypeCurrency;
use App\Models\Types\TypeOperation;
use App\Modules\Documents\JsonProcessor;
use App\Modules\Documents\QrDocument;
use App\Services\Invoice\HealthValidatorService;
use App\Services\Invoice\PointOfSaleService;
use App\Services\Invoice\SoftwareManufacturerService;
use App\Services\ShowroomService;
use App\Traits\DocumentListValuesTrait;
use App\Traits\ElectronicDocumentsTrait;
use Carbon\Carbon;
use Exception;
use Lopezsoft\UBL21dian\Templates\SOAP\SendBillAsync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendBillSync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendTestSetAsync;
use Lopezsoft\UBL21dian\XAdES\SignAdjustmentNote;
use Lopezsoft\UBL21dian\XAdES\SignCreditNote;
use Lopezsoft\UBL21dian\XAdES\SignDebitNote;
use Lopezsoft\UBL21dian\XAdES\SignDocumentSupport;
use Lopezsoft\UBL21dian\XAdES\SignInvoice;

class ElectronicDocumentoProcessor
{
    use ElectronicDocumentsTrait, DocumentListValuesTrait;

    /**
     * @throws Exception
     */
    public function process($request): object
    {
        try {
            $document_signature = $request->document_signature ?? null;
            $currency_id        = $request->currency_id ?? 272;
            $user               = $request->user;
            $company            = $request->company;
            $send_to_queue      = $request->send_to_queue ?? 0; // 1: Para enviar el documento a procesar en la cola
            $language           = $request->language_id;
            $operation_type_id  = $request->operation_type_id;
            $type_document_id   = $request->type_document_id;
            // Method async
            $async              = $request->async;
            // Notes
            $notes              = $request->notes;
            $order_number       = $request->order_number;
            $payment_value      = $request->payment_value;
            $software           = $request->software;
            // Resolution
            $resolution         = $request->resolution;

            $document_number     = $this->getDocumentNumber($request, $resolution);

            $company->dv        = VerificationDigit::getDigit(intval($company->dni ?? 0.0));
            // Type document
            $language           = Language::findOrFail($language);
            // Health
            $hasHealth          = isset($request->health);
            $health             = null;
            if ($hasHealth) {
                $health         = HealthValidatorService::validator($request);
            }
            // Operation type
            $operationType      = TypeOperation::findOrFail($operation_type_id);
            $customer           = Customer::getCustomer($request);
            // Type document
            $typeDocument       = $resolution->type_document;
            // Date time
            $date               = $this->getDate($request);
            $inputDate          = Carbon::parse($date);
            $today              = Carbon::now();
            if ($inputDate->toDateString() !== $today->toDateString()) {
                throw new Exception("La fecha ({$inputDate->format('Y-m-d')}) del documento no puede ser distinta a la fecha actual.", 400);
            }
            $expiration_date    = null;
            if ($request->expiration_date) {
                $expiration_date    = $this->getExpirationDate($request);
            }
            $time               = $this->getTime($request);
            $due_date           = null;
            if ($request->due_date) {
                $due_date           = $this->getDueDate($request);
            }
            // Payment form
            $paymentForm        = $this->getPayment($request);
            // reference_number_payment
            $reference_number_payment = $request->reference_number_payment ?? null;
            // currency
            $currency           = TypeCurrency::findOrFail($currency_id);
            if (!$currency) throw new Exception("No se encontró la moneda con id {$currency_id}", 404);
            // Allowance charges
            $allowanceCharges   = Charges::getCharges($request);
            // Tax totals
            $taxClass       = new Taxes();
            $taxTotals      = $taxClass->getTaxTotals($request);

            $taxRetentions  = $taxClass->getTaxRetentions($request);
            // Legal monetary totals
            $legalMonetaryTotals            = LegalMonetaryTotals::getTotals($request);
            if ($legalMonetaryTotals->payable_amount > 224 && $company->dni === '901091403' && env('APP_ENV') !== 'local') {
                throw new Exception("El valor total del documento no puede ser mayor a 224 pesos. Este límite es para pruebas.", 400);
            }
            // Prepaid payments
            $prepaidPayments                = LegalMonetaryTotals::getPrepaidPayments($request);
            // Invoice lines
            $documentLines                  = InvoiceLines::getLines($request);
            $orderReference                 = DocumentReference::getOrderReference($request);
            // discrepancy_response
            $discrepancyResponse            = $this->getDiscrepancyResponse($request);
            // Invoice period
            $invoicePeriod                  = DocumentReference::getInvoicePeriod($request);
            // Billing reference
            $billingReference               = DocumentReference::getBillingReference($request);
            $additionalDocumentReferences   = null;
            $paymentExchangeRate            = $this->getPaymentExchangeRate($request, $company->id, $currency_id);
            // Equivalent document
            $pointsOfSale                   = PointOfSaleService::get($request);
            $softwareManufacturer           = null;
            if (in_array($type_document_id, [20, 25, 27, 60], true)) { // D.E POS Electrónico
                $softwareManufacturer           = SoftwareManufacturerService::get($request);
            }
            // Cine
            $showroomInformation            = ShowroomService::processShowroomInfo($request);
            // Create XML
            $invoice = $this->createXML(compact(
                'showroomInformation',
                'user',
                'company',
                'customer',
                'taxTotals',
                'taxRetentions',
                'resolution',
                'paymentForm',
                'expiration_date',
                'typeDocument',
                'documentLines',
                'allowanceCharges',
                'legalMonetaryTotals',
                'date',
                'time',
                'due_date',
                'notes',
                'document_number',
                'currency',
                'operationType',
                'pointsOfSale',
                'softwareManufacturer',
                'language',
                'invoicePeriod',
                'orderReference',
                'additionalDocumentReferences',
                'discrepancyResponse',
                'billingReference',
                'paymentExchangeRate',
                'software',
                'health',
                'hasHealth',
                'prepaidPayments',
                'reference_number_payment',
            ));

            // Signature XML
            $signInvoice = match ($type_document_id) {
                15      => new SignAdjustmentNote($company->certificate->path, $company->certificate->password), // Nota de ajuste
                11      => new SignDocumentSupport($company->certificate->path, $company->certificate->password), // Documento soporte
                5, 94   => new SignCreditNote($company->certificate->path, $company->certificate->password), // Nota de crédito
                4, 93   => new SignDebitNote($company->certificate->path, $company->certificate->password), // Nota de débito
                default => new SignInvoice($company->certificate->path, $company->certificate->password), // Factura
            };

            $signInvoice->softwareID    = $software->identification;
            $signInvoice->pin           = $software->pin;

            if ($type_document_id === 9 || $type_document_id === 10) { // Factura por contingencia
                $signInvoice->contingency = true;
            } else if (in_array($type_document_id, $this->invoiceListValues, true)) { // Factura nacional o de exportación
                $technicalKey = $resolution->technical_key ?? $software->technical_key;
                $signInvoice->technicalKey = $technicalKey;
            }

            if ($software->environment->code === 1) {  // Production
                if ($async) {
                    $sendBill = new SendBillASync($company->certificate->path, $company->certificate->password);
                    $ShippingMethod = 'SendBillASync';
                } else {
                    $sendBill = new SendBillSync($company->certificate->path, $company->certificate->password);
                    $ShippingMethod = 'SendBillSync';
                }
            } else {
                $ShippingMethod = 'SendTestSetAsync';
                $sendBill = new SendTestSetAsync($company->certificate->path, $company->certificate->password);
                $sendBill->testSetId = $software->testsetid;
            }
            $sendBill->To           = $software->url;
            $sendBill->fileName     = "{$document_number}.xml";
            $sendBill->contentFile  = $this->zipBase64($company, $resolution, $signInvoice->sign($invoice));
            $qrData                 = $signInvoice->getQRData();
            $zipKey                 = $signInvoice->getUUID();
            $xmlDocumentKey         = $zipKey;
            $XmlBase64      = $this->getXML();
            $XmlName        = $this->getNameXML();
            $xmlPath        = $this->getPathXML();
            $zipPath        = $this->getPathZIP();
            // Guarda la información del documento para la representación gráfica.
            $payments       = $paymentForm;
            $paymentsData   = [];
            foreach ($payments as $payment) {
                $paymentsData[] = (object)[
                    'payment_method_id'    => $payment->paymentMethod->id ?? 1,
                    'means_payment_id'     => $payment->meansPayment->id ?? 10,
                    'value_paid'           => $payment->value_paid ?? 0,
                    'currency_id'          => $payment->currency_id ?? 272,
                    'payment_due_date'     => $payment->payment_due_date ?? null,
                ];
            }
            /**
             *  Envía el documento a la DIAN, siempre y cuando no se envíe a procesar en segundo plano
             */
            $response   = null;
            if (!$send_to_queue) {
                $response       = $sendBill->signToSend()->getResponseToObject();
                if ($software->environment->code === 1) { // Production
                    if ($async) {
                        $zipKey         = $response->Envelope->Body->SendBillAsyncResponse->SendBillAsyncResult->ZipKey;
                    } else {
                        $dianResponse   = $response->Envelope->Body->SendBillSyncResponse->SendBillSyncResult;
                        $xmlDocumentKey = $dianResponse->XmlDocumentKey;
                    }
                } else {
                    $zipKey = $response->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey;
                }
            }

            $jsonData = json_encode([
                'customer' => $customer,
                'currencyId' => $currency_id,
                'points' => $customer->points ?? 0,
                'dni' => $customer->company->dni,
                "language" => $request->language_id,
                "operationTypeId" => $request->operation_type_id,
                "typeDocumentId" => $request->type_document_id,
                "documentNumber" => $document_number,
                "invoiceDate" => $date,
                "invoiceTime" => $time,
                "expirationDate" => $expiration_date,
                "dueDate" => $due_date,
                "notes" => $notes,
                "cufe" => $xmlDocumentKey,
                "qrcode" => "",
                "qrDian" => QrDocument::getUrl($software, $xmlDocumentKey),
                "qrData" => base64_encode($qrData),
                "taxRetentions" => $taxRetentions,
                "payment_value" => $payment_value,
                "payment" => $paymentsData,
                "allowanceCharges" => $allowanceCharges,
                "orderReference" => $orderReference,
                "paymentExchangeRate" => $paymentExchangeRate,
                "taxTotals" => $taxTotals,
                "legalMonetaryTotals" => $legalMonetaryTotals,
                "totalPayment" => number_format(floatval($legalMonetaryTotals->payable_amount), 2, ".", ""),
                "totalDiscount" => $allowanceCharges['amount'] ?? 0.00,
                "totalCharges" => $allowanceCharges['amount'] ?? 0.00,
                "lines" => $documentLines,
                'additionalDocumentReferences' => $additionalDocumentReferences,
                "discrepancyResponse" => $discrepancyResponse,
                "billingReference" => $billingReference,
                'pointsOfSale' => $pointsOfSale,
                'softwareManufacturer' => $softwareManufacturer,
                'health' => $health,
                'prepaidPayments' => $prepaidPayments,
                'invoicePeriod' => $invoicePeriod,
                'reference_number_payment' => $reference_number_payment,
                'showroomInformation' => $showroomInformation,
                'signatureDocument' => $document_signature,
            ], JSON_THROW_ON_ERROR);

            $SAVE_JSON_FILE = env('SAVE_JSON_FILE', false);
            if ($SAVE_JSON_FILE) {
                JsonProcessor::storeData($XmlName, $company, $jsonData, $typeDocument);
            }

            return (object) [
                'XmlBase64'         => $XmlBase64,
                'XmlDocumentName'   => $XmlName,
                'ZipKey'            => $zipKey, // Para pruebas - No cambiar
                'ShippingMethod'    => $ShippingMethod,
                'dianResponse'      => $response,
                'jsonData'          => $jsonData,
                'document_number'   => $document_number,
                'XmlDocumentKey'    => $xmlDocumentKey,
                'xmlPath'           => $xmlPath,
                'zipPath'           => $zipPath,
                'order_number'      => $order_number,
                'message'           => ($send_to_queue) ? 'Se ha enviado el documento a procesar en segundo plano.' :
                    'Solicitud procesada por la DIAN.',
                'send_to_queue'     => $send_to_queue,
            ];
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
