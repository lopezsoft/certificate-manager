<?php

namespace App\Services\Mail;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailLogsService
{
    public static function findAll(): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $result = DB::table('email_logs_view')
                ->where('company_id', $company->id);

            return HttpResponseMessages::getResponse([
                'dataRecords' => $result->paginate($request->limit ?? 20),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function findOne($id): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $result     = DB::table('email_logs_view')
                ->where('id', $id)
                ->where('company_id', $company->id)
                ->first();

            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => $result,
                    'totalRecords' => $result->count(),
                ]
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function findByDocumentId(Request $request, $documentId): JsonResponse
    {
        try {
            $company        = CompanyQueries::getCompany();
            $typeDocumentId = $request->type_document_id;
            $result = DB::table('email_logs_view')
                ->where('document_id', $documentId)
                ->where('company_id', $company->id)
                ->where('type_document_id', $typeDocumentId)
                ->orderBy('created_at', 'desc');

            return HttpResponseMessages::getResponse([
                'dataRecords' => $result->paginate($request->limit ?? 10),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
