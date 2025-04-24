<?php

namespace App\Modules\Documents;

use App\Models\Company;
use App\Models\ShippingHistory;
use App\Services\AttachedDocumentService;
use App\Services\PdfDocumentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MailMessages
{
    /**
     * @throws Exception
     */
    public static function getElectronicDocumentMessage(Request $request, Company $company, $jsonData, $shipping): object
    {
        try {
            $trackId        = $jsonData->cufe ?? null;
            // Guarda el contenedor de documentos adjuntos
            if(!AttachedDocumentService::isExists($shipping)){
                (new AttachedDocument())->process($request, $trackId);
                $shipping           = ShippingHistory::find($shipping->id);
            }
            // Guarda el PDF de la factura
            /**
             * Cuando el reporte no se ha generado, se genera para evitar el envío en blanco del PDF de la factura
             */
            if(!PdfDocumentService::isExists($shipping) && (in_array($request->type_id, [1, 3, 4, 5, 6], true))){
                (new DocumentPdf())->process($request, $trackId);
                $shipping           = ShippingHistory::find($shipping->id);
            }
            $documentName       = str_replace('.xml', '', $shipping->XmlDocumentName);
            $customer           = $jsonData->customer;
            try {
                $logo = str_replace('/storage/', '', $company->image);
                if (!Storage::disk('public')->exists($logo)) {
                    $logo = "https://matias.com.co/assets/img/brand/invoice-app.png";
                } else {
                    $logo = Storage::disk('public')->path($logo);
                    if($logo === '/var/www/vhosts/matias-api.com/apiv2/storage/app/public/') {
                        $logo = "https://matias.com.co/assets/img/brand/invoice-app.png";
                    }
                }
            } catch (\Exception $e) {
                $logo = "https://matias.com.co/assets/img/brand/invoice-app.png";
            }

            // Guarda el contenedor de documentos adjuntos
            AttachedDocument::storeZip($shipping);
            $shipping           = ShippingHistory::find($shipping->id);
            $pdfContent         = ContentDocument::getPdfContent($shipping);
            $attachedZipContent = ContentDocument::getAttachmentZipContent($shipping);
            $attachedName       = pathinfo($shipping->attachedZipPath, PATHINFO_BASENAME);
            // Cuerpo del mensaje del correo electrónico
            $tradeName          = !empty($company->trade_name) ? $company->trade_name : $company->company_name;
            $typeDocument       = (object) $jsonData->typeDocument;
            $legalMonetaryTotals= (object) $jsonData->legalMonetaryTotals;
            return    (object)[
                'title'                 => $jsonData->typeDocument->voucher_name,
                'documentName'          => $documentName,
                'attachedName'          => $attachedName,
                'customer'              => $customer,
                'voucher_name'          => mb_strtoupper($typeDocument->voucher_name),
                'invoice_name'          => mb_strtoupper($typeDocument->voucher_name),
                'company'               => $company,
                'company_name'          => $customer->company_name,
                'company_image'         => $logo,
                'invoice_nro'           => $shipping->document_number,
                'total'                 => "{$jsonData->currency->Symbol} ".number_format($legalMonetaryTotals->payable_amount ?? 0, 2, ".",","),
                'info'                  => $company->email,
                'url'                   => $trackId ? "https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey={$trackId}" : null,
                'pdf'                   => $pdfContent->data ?? null,
                'attached'              => base64_encode($attachedZipContent),
                'document_id'           => $shipping->id,
                'type_document_id'      => $shipping->type_document_id,
                'subject'               => "{$company->dni};{$company->company_name};{$shipping->document_number};{$typeDocument->code};{$tradeName}",
            ];
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
