<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Models\CertificateRequest;
use App\Models\FileManager;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CertificateRequestFilesService
{
    public function createFile(Request $request, $certificateRequestId): JsonResponse
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
            if ($files->count() >= 6) {
                throw new Exception("No se pueden subir más de 6 archivos.", 400);
            }
            $fileSize = $file->getSize();
            if ($fileSize > 2 * 1024 * 1024) {
                throw new Exception("El tamaño del archivo no puede superar los 2MB.", 400);
            }
            $company        = CompanyQueries::getCompany();
            $pin            = $request->input('pin');
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
            DB::beginTransaction();
            $file = FileManager::create([
                'certificate_request_id'    =>  $certificateRequestId,
                'file_name'         =>  $fileName,
                'file_path'         =>  $path,
                'extension_file'    =>  $format,
                'mime_type'         =>  $mimeType,
                'file_size'         =>  $sizeFile,
                'last_modified'     =>  date('Y-m-d H:i:s', $lastModified),
                'status'            =>  'COMPLETED',
                'document_type'     => $request->input('document_type') ?? 'ATTACHED'
            ]);
            if ($pin && $format == 'zip') {
                // Nombre del archivo sin la extensión
                $fileNameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
                $extractToPath  = $disk->path("{$basePath}/zip");
                $password       = $certificateRequest->dni;
                if ($certificateRequest->type_organization_id == 1) {
                    $password = "{$certificateRequest->dni}{$certificateRequest->dv}";
                }
                $zipFilePath    = $disk->path($path);
                if ((new ZipExtractorService())->extract((object)[
                    'zipFilePath'     => $zipFilePath,
                    'password'        => $password,
                    'extractToPath'   => $extractToPath,
                    'fileName'        => $fileNameWithoutExtension,
                ])) {
                    $allFiles = $disk->allFiles("{$basePath}/zip");
                    $content = null;
                    foreach ($allFiles as $allFile) {
                        $content = base64_encode($disk->get($allFile));
                        $extension = pathinfo($allFile, PATHINFO_EXTENSION);
                        if ($extension == 'p12' || $extension == 'pfx') {
                            break;
                        }
                    }
                    if (!$content) {
                        throw new Exception("No se ha encontrado el archivo P12 o PFX en el ZIP.", 400);
                    }

                    $expirationDate = CertificateValidatorService::getExpirationDate($content, $pin);
                    $certificateRequest->update([
                        'expiration_date' => $expirationDate,
                        'pin'             => $pin,
                    ]);
                    // Eliminar el archivo ZIP extraído
                    $disk->deleteDirectory("{$basePath}/zip");
                }
            }
            DB::commit();
            return HttpResponseMessages::getResponse([
                'message' => 'Archivo creado correctamente.',
                'dataRecords' => [
                    'data' => [$file],
                ],
            ]);
        } catch (Exception $e) {
            DB::rollBack();
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
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
