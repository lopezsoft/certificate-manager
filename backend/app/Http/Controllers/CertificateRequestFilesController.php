<?php

namespace App\Http\Controllers;

use App\Services\CertificateRequestFilesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateRequestFilesController extends Controller
{
    public function createFile(Request $request, $certificateRequestId): JsonResponse
    {
        return (new CertificateRequestFilesService())->createFile($request, $certificateRequestId);
    }

    public function deleteFile($id, $fileId): JsonResponse
    {
        return (new CertificateRequestFilesService())->deleteFile($id, $fileId);
    }
}
