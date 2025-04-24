<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Exports\Reports\InvoiceReports;
use App\Interfaces\DocumentsInterface;
use App\Models\ShippingHistory;
use App\Modules\Company\CompanyQueries;
use App\Services\PdfDocumentService;
use App\Traits\DocumentListValuesTrait;
use Exception;
use Illuminate\Http\Request;

class DocumentPdf implements DocumentsInterface
{
    use DocumentListValuesTrait;
    public function process(Request $request, $trackId): object
    {
        try {
            $regenerate = $request->regenerate ?? 0;
            $regenerate = intval($regenerate) == 1;
            $company    = CompanyQueries::getCompany();
            $pdf        = null;
            $shipping   = ShippingHistory::where('XmlDocumentKey', $trackId)
                            ->where('company_id', $company->id)
                            ->orderBy('id', 'DESC')
                            ->first();
            if(!$shipping) throw new Exception("No hay datos para generar el PDF.", 404);
            if (!$regenerate && PdfDocumentService::isExists($shipping)) {
                return ContentDocument::getPdfContent($shipping);
            }
            $request->type_id   = TypeDocumentIdSoftware::getId($shipping->type_document_id);
            $jsonData           = $shipping->jsonData;
            if ($jsonData && (in_array($request->type_id, $this->documentsPDFList))) {
                $software           = CompanyQueries::getSoftware($request, $company);
                $params             = (object) [
                    'company'       => $company,
                    'software'      => $software,
                    'trackId'       => $trackId,
                    'shipping'      => $shipping,
                ];
                $pdf                = (new InvoiceReports())->getInvoice($params);
                $shipping->pdfPath  = $pdf->path;
                $shipping->save();
            }
            return HttpResponseMessages::getResponse([
                'message'           => ($pdf) ? 'PDF generado correctamente.' : 'No se generó el PDF.',
                'pdf'               => $pdf,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

}
