<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Models\ShippingHistory;
use App\Modules\Company\CompanyQueries;
use App\Queries\CallExecute;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShippingQueryService
{
    public function getLastDocument(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $query      = ShippingHistory::query()
                ->where('company_id', $company->id)
                ->orderBy('id', 'desc');
            $resolution     = $request->input('resolution');
            $prefix         = $request->input('prefix');
            if(!$resolution && !$prefix) {
                throw new Exception("No se ha enviado el número de resolución o el prefijo del documento.", 400);
            }
            if($resolution) {
                $query->whereHas('resolution', function ($query) use ($resolution) {
                    $query->where('resolution_number', $resolution);
                });
            }
            if($prefix) {
                $query->whereHas('resolution', function ($query) use ($prefix) {
                    $query->where('prefix', $prefix);
                });
            }
            $dataRecords = $query->first();
            return HttpResponseMessages::getResponse([
                'dataRecord' => $dataRecords,
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }

    }
    /**
     * @throws \Exception
     */
    public function getDocuments(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $query      = ShippingHistory::query()
                            ->where('company_id', $company->id)
                            ->orderBy('id', 'desc')
                            ->with(['document', 'operationType', 'resolution']);
            $search         = $request->input('query');
            $documentKey    = $request->input('document_key');
            $orderNumber    = $request->input('order_number');
            $resolution     = $request->input('resolution');
            $number         = $request->input('number');
            $prefix         = $request->input('prefix');
            $startDate      = $request->input('start_date');
            $endDate        = $request->input('end_date');
            $type_document_id= $request->input('document_type') ?? 0;
            $statusId       = $request->input('document_status') ?? -1;
            if ($resolution)
                $query->whereHas('resolution', function ($query) use ($resolution) {
                    $query->where('resolution_number', $resolution);
                });
            if ($number)
                $query->where('document_number', $number);
            if ($search) {
                $query->where('document_number', 'like', "%$search%")
                    ->orWhere('order_number', 'like', "%$search%");
            }
            if ($documentKey) {
                $query->where('XmlDocumentKey', $documentKey);
            }
            if ($orderNumber) {
                $query->where('order_number', $orderNumber);
            }
            if($prefix) {
                $query->whereHas('resolution', function ($query) use ($prefix) {
                    $query->where('prefix', $prefix);
                });
            }
            if ($startDate) {
                $query->where('invoice_date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('invoice_date', '<=', $endDate);
            }
            if ($type_document_id) {
                $query->where('type_document_id', $type_document_id);
            }
            if($statusId >= 0) {
                $query->where('is_valid', $statusId);
            }
            // Realizar la paginación
            $limit = $request->input('limit', 20);
            if ($limit > 50) {
                $limit = 50;
            }
            $query->where('company_id', $company->id);

            // Preparar y enviar la respuesta
            return HttpResponseMessages::getResponse([
                'dataRecords' => $query->paginate($limit),
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function getDocumentStatus(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $query      = ShippingHistory::query()
                ->where('company_id', $company->id)
                ->orderBy('id', 'desc')
                ->with(['document', 'operationType', 'resolution']);
            $orderNumber    = $request->input('order_number');
            $resolution     = $request->input('resolution');
            $number         = $request->input('number');
            $prefix         = $request->input('prefix');
            if ($resolution)
                $query->whereHas('resolution', function ($query) use ($resolution, $number) {
                    $query->where('resolution_number', $resolution);
                });
            if ($number)
                $query->where('document_number', $number);
            if ($orderNumber) {
                $query->where('order_number', $orderNumber);
            }
            if($prefix) {
                $query->whereHas('resolution', function ($query) use ($prefix) {
                    $query->where('prefix', $prefix);
                });
            }
            $document =  $query->where('company_id', $company->id)->first();
            // Verificar si se encontró el documento
            if(!$document) {
                throw new Exception("No se encontró el documento.", 404);
            }

            // Preparar y enviar la respuesta
            return HttpResponseMessages::getResponse([
                'document' => [
                    'uuid' => $document->uuid,
                    'document_number' => $document->document_number,
                    'order_number' => $document->order_number,
                    'document_key' => $document->XmlDocumentKey,
                    'document_name' => $document->XmlDocumentName,
                    'is_valid' => (bool)$document->is_valid,
                    'invoice_date' => $document->invoice_date,
                    'qr' => [
                        'qrDian'    => $document->jsonData->qrDian ?? null,
                        'data'      => $document->jsonData->qrData ?? null,
                        "path"      => $document->qrPath ?? null,
                        "url"       => url(Storage::disk('qr')->url("{$document->qrPath}")),
                    ],
                ],
                'status' => $document->is_valid ? 'Validado por la DIAN' : 'Sin validación por la DIAN',
                'message' => 'Consulta exitosa',
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function delete($id): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $query      = ShippingHistory::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->first();
            if(!$query) {
                throw new Exception("No se encontró el documento.", 404);
            }
            if ($query->is_valid) {
                throw new Exception("No se puede eliminar un documento validado por la DIAN.", 400);
            }
            $query->delete();
            return HttpResponseMessages::getResponse([
                'message' => 'Documento eliminado correctamente.',
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getConsume(Request $request): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $type       = $request->input('p_type') ?? 1;
            $dni        = $request->input('_dni');
            $year       = $request->input('p_year') ?? date('Y');
            $query      = CallExecute::execute("sp_document_consume(?, ?, ?, ?)", [
                $company->dni,
                $type,
                $dni,
                $year,
            ]);
            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => $query
                ],
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
