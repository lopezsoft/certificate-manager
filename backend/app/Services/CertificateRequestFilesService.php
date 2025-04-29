<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Models\CertificateRequest;
use App\Models\FileManager;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CertificateRequestFilesService
{
    public function createFile($certificateRequestId): JsonResponse
    {
        try {
            $certificateRequest = CertificateRequest::query()
                ->where('id', $certificateRequestId)
                ->first();
            if (!$certificateRequest) {
                throw new Exception("No se ha encontrado la solicitud de certificado.", 400);
            }
            $file = request()->file('file');
            if (!$file) {
                throw new Exception("No se ha encontrado el archivo.", 400);
            }

            $files = FileManager::query()
                ->where('certificate_request_id', $certificateRequestId)
                ->get();
            if ($files->count() >= 4) {
                throw new Exception("No se pueden subir más de 4 archivos.", 400);
            }
            $fileSize = $file->getSize();
            foreach ($files as $f) {
                // Validar que el tamaño del archivo no supere los 2MB
                if ($f->file_size + $fileSize > 2 * 1024 * 1024 + 50) {
                    throw new Exception("El tamaño del archivo no puede superar los 2MB.", 400);
                }
            }
            $company        = CompanyQueries::getCompany();
            $disk           = Storage::disk('attachment');
            $basePath       = $certificateRequest->base_path;
            if (!$basePath) {
                $year           = date('Y');
                $month          = date('m');
                $dni            = $company->dni;
                $dv             = $company->dv;
                $basePath       = "companies/{$company->id}/{$year}/{$month}/{$dni}{$dv}";
                $disk->makeDirectory($basePath);
            }

            $fileName       = $file->getClientOriginalName();
            $path           = "{$basePath}/{$fileName}";
            $disk->putFileAs($basePath, $file, $fileName);

            $format         = pathinfo($path, PATHINFO_EXTENSION);
            $mimeType       = $disk->mimeType($path);
            $sizeFile       = $disk->size($path);
            $lastModified   = $disk->lastModified($path);
            $file = FileManager::create([
                'certificate_request_id'    =>  $certificateRequestId,
                'file_name'         =>  $fileName,
                'file_path'         =>  $path,
                'extension_file'    =>  $format,
                'mime_type'         =>  $mimeType,
                'file_size'         =>  $sizeFile,
                'last_modified'     =>  date('Y-m-d H:i:s', $lastModified),
                'status'            =>  'COMPLETED',
            ]);
            return HttpResponseMessages::getResponse([
                'message' => 'Archivo creado correctamente.',
                'dataRecords' => [
                    'data' => [$file],
                ],
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function deleteFile($id, $fileId): JsonResponse
    {
        try {
            $file = FileManager::query()
                ->where('id', $fileId)
                ->where('certificate_request_id', $id)
                ->first();
            if (!$file) {
                throw new Exception("No se ha encontrado el archivo.", 400);
            }
            Storage::disk('attachment')->delete($file->file_path);
            $file->delete();
            return HttpResponseMessages::getResponse([
                'message' => 'Archivo eliminado correctamente.',
                'dataRecords' => [
                    'data' => [$file],
                ],
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }

    }

}
