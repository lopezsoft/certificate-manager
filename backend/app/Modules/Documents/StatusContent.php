<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Exports\Reports\InvoiceReports;
use App\Jobs\Emails\EmailSendingDocumentJob;
use App\Models\Email\DocumentEmailJob;
use App\Models\JsonData;
use App\Models\User;
use App\Modules\Documents\Invoice\Customer;
use App\Services\AttachedDocumentService;
use App\Services\DianResponseService;
use Exception;
use Illuminate\Http\Request;

class StatusContent
{

    /**
     * @throws Exception
     */
    public static function getContent(Request $request, $responseDocument, $shipping): \Illuminate\Http\JsonResponse
    {
        $XmlBase64              = $responseDocument->XmlBase64 ?? $responseDocument->XmlBase64Bytes ?? null;
        $response               = $responseDocument->dianResponse;
        $company                = $request->company;
        $software               = $request->software;
        $dianResponse           = DianResponseService::getResponse($response);
        $isValid                = ($dianResponse->IsValid === "true" && $dianResponse->StatusCode === "00");
        $attachedDocument       = null;
        $qr                     = null;
        $pdf                    = null;
        $graphicRepresentation  = intval($request->graphic_representation) ?? 0;
        $sendEmail              = intval($request->send_email) ?? 0;
        if ($shipping && $isValid) {
           $qr                      = QrDocument::getUrl($software, $dianResponse->XmlDocumentKey);
            try {
                if (!$XmlBase64) {
                    $XmlBase64          = ContentDocument::getXmlContent($shipping);
                }
                $document_number    = $shipping->document_number;
                $jData              = JsonData::query()->where('shipping_id', $shipping->id)->first();
                // Si el tipo de documento es diferente a 2 (Nómina) se procede a realizar el proceso
                if($jData && $request->type_id === 2) {
                    $qr = (object) [
                        'qrDian'=> $qr,
                    ];
                } else if ($jData) {
                    $jsonData               = (object) $jData->jdata;
                    $dni                    = $jsonData->dni ?? null;
                    if (isFinalConsumer($dni)) {
                        $dni = null;
                    }
                    $customer               = null;
                    if (AttachedDocumentService::isExists($shipping)){
                        $attachedDocument = AttachedDocumentService::extractWhenExists($shipping);
                    } else {
                        $user                   = User::query()->find($shipping->user_id);
                        if ($dni) {
                            if(isset($jsonData->customer)) {
                                $customerData = $jsonData->customer;
                                $customer = is_object($customerData) ? $customerData->company : ($customerData['company'] ?? []);
                                $customer = Customer::getCustomerData((array) $customer);
                            } else {
                                $customer               = AttachedDocumentService::getCustomer($jsonData->dni);
                            }
                            $params                 = (object) [
                                'response'          => $response,
                                'XmlBase64'         => $XmlBase64,
                                'company'           => $company,
                                'document_number'   => $document_number,
                                'customer'          => $customer,
                                'user'              => $user,
                            ];
                            $attachedDocument           = (new AttachedDocument())->store($params);
                            $shipping->attachedPath     = $attachedDocument->path;
                            $shipping->attachedZipPath  = $attachedDocument->pathZip;
                        }
                    }

                    $cufe           = $dianResponse->XmlDocumentKey;
                    $jsonData->cufe = $cufe;
                    $jsonData->qr   = $qr;
                    $qr             = QrDocument::store($jsonData, $company, $shipping);

                    $jData->jdata   = $jsonData;
                    $jData->save();

                    $shipping->qrPath = $qr->path;
                    $shipping->save();
                    // Genera el PDF de la factura
                    if ($graphicRepresentation === 1 || $sendEmail === 1) {
                        $shipping->refresh();
                        $params             = (object) [
                            'company'       => $company,
                            'software'      => $software,
                            'trackId'       => $cufe,
                            'shipping'      => $shipping
                        ];
                        $pdf                = (new InvoiceReports())->getInvoice($params);
                        $shipping->pdfPath  = $pdf->path;
                        $shipping->save();
                    }
                    // Envío del correo electrónico
                    if ($sendEmail === 1) {
                        if ($dni && $customer && isset($customer->email)) {
                            $emailTo    = $customer->email;
                            // Se guarda el registro en la tabla de envío de correos electrónicos
                            $documentEmailJob = DocumentEmailJob::create([
                                'company_id'        => $company->id,
                                'document_id'       => $shipping->id,
                                'type_document_id'  => $shipping->type_document_id,
                                'email_to'          => $emailTo,
                                'created_at'        => now(),
                            ]);
                            EmailSendingDocumentJob::dispatch($documentEmailJob);
                        }
                    }
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        return HttpResponseMessages::getResponse([
            'message'           => $responseDocument->message ?? 'El documento ha sido procesado por la DIAN.',
            'send_to_queue'     => $responseDocument->send_to_queue ?? 0,
            'XmlDocumentKey'    => $dianResponse->XmlDocumentKey,
            'response'          => $dianResponse,
            'XmlBase64Bytes'    => $XmlBase64,
            'AttachedDocument'  => $attachedDocument,
            'qr'                => $qr,
            'pdf'               => $pdf,
        ]);
    }
}
