<?php

namespace App\Http\Controllers;

use App\Services\CertificateRequestFilesService;
use Illuminate\Http\JsonResponse;

class CertificateRequestFilesController extends Controller
{
    public function createFile($certificateRequestId): JsonResponse
    {
        return (new CertificateRequestFilesService())->createFile($certificateRequestId);
    }

    public function deleteFile($id, $fileId): JsonResponse
    {
        return (new CertificateRequestFilesService())->deleteFile($id, $fileId);
    }
}
