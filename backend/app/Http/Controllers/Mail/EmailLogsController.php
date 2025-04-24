<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Services\Mail\EmailLogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class EmailLogsController extends Controller
{
    /**
     * Find one email log by id
     * Busca un registro de email por id
     */

    public function findOne($id): JsonResponse
    {
        return EmailLogsService::findOne($id);
    }

    /**
     * Find all email logs
     * Busca todos los registros de email
     */

    public function findAll(): JsonResponse
    {
        return EmailLogsService::findAll();
    }

    /**
     * Find email logs by document id
     * Busca los registros de email por id de documento
     */

    public function findByDocumentId(Request $request, $documentId): JsonResponse
    {
        return EmailLogsService::findByDocumentId($request, $documentId);
    }
}
