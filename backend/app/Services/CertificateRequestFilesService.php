<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Events\CertificateFileUploaded;
use App\Jobs\ProcessCertificateJob;
use App\Models\CertificateRequest;
use App\Models\FileManager;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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

            // Validar tipo MIME real del archivo (no solo la extensión)
            $mimeValidator = Validator::make(
                ['file' => $file],
                ['file' => [
                    'required',
                    'mimetypes:application/pdf,image/jpeg,image/png,image/gif,application/zip,application/x-zip-compressed',
                ]],
                ['file.mimetypes' => 'Tipo de archivo no permitido. Solo se aceptan PDF, imágenes (JPG, PNG, GIF) y archivos ZIP.']
            );
            if ($mimeValidator->fails()) {
                throw new Exception($mimeValidator->errors()->first('file'), 422);
            }
            $company        = CompanyQueries::getCompany();
            $pin            = $request->input('pin');
            $disk           = Storage::disk(config('certificate.storage.legacy_disk', 'attachment'));
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
            
            event(new CertificateFileUploaded(
                certificateRequestId: $certificateRequestId,
                companyId: $certificateRequest->company_id,
                fileId: $file->id,
                fileName: $file->file_name,
                documentType: $file->document_type,
            ));

            // Process image files with AI if they are supported formats
            $this->processFileWithAI($file, $certificateRequestId);
            
            // Check if we should trigger comprehensive document analysis
            $this->checkForComprehensiveAnalysis($certificateRequestId);
            
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
            Storage::disk(config('certificate.storage.legacy_disk', 'attachment'))->delete($file->file_path);
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

    /**
     * Process uploaded file with AI if it's a supported image format
     *
     * @param FileManager $file
     * @param int $certificateRequestId
     * @return void
     */
    private function processFileWithAI(FileManager $file, int $certificateRequestId): void
    {
        try {
            // Get supported formats from configuration
            $supportedFormats = config('ai.processing.supported_formats', ['jpg', 'jpeg', 'png', 'pdf']);
            $extension = strtolower($file->extension_file);

            // Only process supported image formats
            if (!in_array($extension, $supportedFormats)) {
                Log::info("File extension '{$extension}' not supported for AI processing", [
                    'file_id' => $file->id,
                    'certificate_request_id' => $certificateRequestId
                ]);
                return;
            }

            // Check if AI services are properly configured
            if (empty(config('ai.google_vision.project_id')) || empty(config('ai.gemini.api_key'))) {
                Log::warning("AI services not properly configured, skipping AI processing", [
                    'file_id' => $file->id,
                    'certificate_request_id' => $certificateRequestId
                ]);
                return;
            }

            // Check if file exists before processing
            $disk = Storage::disk(config('certificate.storage.legacy_disk', 'attachment'));
            if (!$disk->exists($file->file_path)) {
                Log::error("File not found for AI processing", [
                    'file_id' => $file->id,
                    'file_path' => $file->file_path,
                    'certificate_request_id' => $certificateRequestId
                ]);
                return;
            }

            // Dispatch the AI processing job in the background
            ProcessCertificateJob::dispatch(
                $file->file_path,
                auth()->id(),
                $certificateRequestId,
                [
                    'file_id' => $file->id,
                    'generate_email' => false, // Set to true if you want to generate emails automatically
                    'email_type' => 'notification',
                    'recipient_name' => $this->getRecipientName($certificateRequestId),
                    'auto_populate_data' => true // Flag to indicate we want to auto-populate extracted data
                ]
            );

            Log::info("AI processing job dispatched successfully", [
                'file_id' => $file->id,
                'certificate_request_id' => $certificateRequestId,
                'file_path' => $file->file_path
            ]);

        } catch (Exception $e) {
            Log::error("Error dispatching AI processing job", [
                'error' => $e->getMessage(),
                'file_id' => $file->id,
                'certificate_request_id' => $certificateRequestId
            ]);
        }
    }

    /**
     * Get recipient name for email generation
     *
     * @param int $certificateRequestId
     * @return string
     */
    private function getRecipientName(int $certificateRequestId): string
    {
        try {
            $certificateRequest = CertificateRequest::find($certificateRequestId);
            return $certificateRequest ? $certificateRequest->legal_representative : 'Estimado usuario';
        } catch (Exception $e) {
            Log::error("Error getting recipient name", [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ]);
            return 'Estimado usuario';
        }
    }

    /**
     * Check if comprehensive document analysis should be triggered
     *
     * @param int $certificateRequestId
     * @return void
     */
    private function checkForComprehensiveAnalysis(int $certificateRequestId): void
    {
        try {
            // Count total documents for this certificate request
            $totalFiles = FileManager::where('certificate_request_id', $certificateRequestId)
                ->where('document_type', 'ATTACHED')
                ->whereIn('extension_file', ['jpg', 'jpeg', 'png', 'pdf'])
                ->count();

            // Trigger comprehensive analysis if we have multiple documents (likely a complete set)
            if ($totalFiles >= 2) {
                Log::info("Triggering comprehensive document analysis", [
                    'certificate_request_id' => $certificateRequestId,
                    'total_files' => $totalFiles
                ]);

                // Dispatch comprehensive analysis in background with delay to allow file processing to complete
                ProcessCertificateJob::dispatch(
                    '', // Empty path since we're analyzing all files
                    auth()->id(),
                    $certificateRequestId,
                    [
                        'comprehensive_analysis' => true,
                        'analysis_trigger' => 'file_upload',
                        'total_files' => $totalFiles
                    ]
                )->delay(now()->addSeconds(30)); // 30 second delay to ensure file processing is complete
            }

        } catch (Exception $e) {
            Log::error("Error checking for comprehensive analysis", [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ]);
        }
    }
}
