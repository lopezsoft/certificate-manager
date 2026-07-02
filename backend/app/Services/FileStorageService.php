<?php

namespace App\Services;

use App\Events\CertificateFileUploaded;
use App\Jobs\ProcessCertificateJob;
use App\Models\FileManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    public function __construct(
        private readonly Base64DecoderService $decoder
    ) {}

    public function storeBase64Files(
        array $files,
        string $folderName,
        int $certificateId,
        ?Request $request = null
    ): array {
        $createdFiles = [];
        $disk = Storage::disk(config('certificate.storage.disk'));

        foreach ($files as $file) {
            // Obtener el Base64 (obligatorio)
            $base64String = $file['base64'] ?? null;
            if (!$base64String) {
                throw new Exception("El contenido en base64 es requerido para cada archivo.", 400);
            }

            // Decodificar el Base64
            try {
                $metadata = $this->decoder->decodeWithMetadata($base64String);
                $binaryContent = $metadata['binary_content'];
                $mimeType = $metadata['mime_type'];
            } catch (\Exception $e) {
                throw new Exception("Error al decodificar el archivo: " . $e->getMessage(), 400);
            }

            // Obtener o generar nombre del archivo
            $fileName = $file['name'] ?? null;
            if (!$fileName) {
                $extension = $this->getExtensionFromMimeType($mimeType);
                $fileName = 'document_' . Str::uuid() . '.' . $extension;
            }

            // Guardar archivo
            $path = "{$folderName}/{$fileName}";
            $disk->put($path, $binaryContent);

            // Obtener document_type (prioridad: request > file > default)
            $documentType = $file['document_type'] ?? ($request?->input('document_type') ?? 'ATTACHED');

            // Registrar en FileManager
            $fileManager = FileManager::create([
                'certificate_request_id' => $certificateId,
                'file_name'              => $fileName,
                'file_path'              => $path,
                'extension_file'         => pathinfo($fileName, PATHINFO_EXTENSION),
                'mime_type'              => $mimeType,
                'file_size'              => strlen($binaryContent),
                'last_modified'          => date('Y-m-d H:i:s'),
                'status'                 => 'COMPLETED',
                'document_type'          => $documentType,
            ]);

            // Procesar ZIP con PIN si aplica
            $pin = $file['pin'] ?? ($request?->input('pin') ?? null);
            if ($pin && pathinfo($fileName, PATHINFO_EXTENSION) === 'zip') {
                $this->processZipFile($fileManager, $binaryContent, $folderName, $pin);
            }

            // Disparar evento
            event(new CertificateFileUploaded(
                certificateRequestId: $certificateId,
                companyId: $fileManager->certificateRequest->company_id,
                fileId: $fileManager->id,
                fileName: $fileManager->file_name,
                documentType: $fileManager->document_type,
            ));

            // Procesar con IA
            $this->processFileWithAI($fileManager, $certificateId);

            $createdFiles[] = $fileManager;
        }

        // Verificar si se debe disparar análisis comprehensivo
        $this->checkForComprehensiveAnalysis($certificateId);

        return $createdFiles;
    }

    /**
     * Procesa archivos ZIP con PIN.
     */
    private function processZipFile(FileManager $fileManager, string $binaryContent, string $folderName, string $pin): void
    {
        try {
            $disk = Storage::disk(config('certificate.storage.disk'));
            $certificateRequest = $fileManager->certificateRequest;
            $fileName = $fileManager->file_name;
            $fileNameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
            $basePath = dirname($fileManager->file_path);
            $extractToPath = storage_path("app/" . config('certificate.storage.disk') . "/{$basePath}/zip");
            
            $password = $certificateRequest->dni;
            if ($certificateRequest->type_organization_id == 1) {
                $password = "{$certificateRequest->dni}{$certificateRequest->dv}";
            }

            $zipFilePath = storage_path("app/" . config('certificate.storage.disk') . "/{$fileManager->file_path}");
            
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
        } catch (Exception $e) {
            Log::error("Error procesando ZIP", [
                'error' => $e->getMessage(),
                'file_id' => $fileManager->id
            ]);
        }
    }

    /**
     * Procesa archivo con IA si es un formato soportado.
     */
    private function processFileWithAI(FileManager $file, int $certificateRequestId): void
    {
        try {
            $supportedFormats = config('ai.processing.supported_formats', ['jpg', 'jpeg', 'png', 'pdf']);
            $extension = strtolower($file->extension_file);

            if (!in_array($extension, $supportedFormats)) {
                return;
            }

            if (empty(config('ai.google_vision.project_id')) || empty(config('ai.gemini.api_key'))) {
                return;
            }

            $disk = Storage::disk(config('certificate.storage.disk'));
            if (!$disk->exists($file->file_path)) {
                return;
            }

            ProcessCertificateJob::dispatch(
                $file->file_path,
                auth()->id(),
                $certificateRequestId,
                [
                    'file_id' => $file->id,
                    'generate_email' => false,
                    'email_type' => 'notification',
                    'recipient_name' => $file->certificateRequest->legal_representative ?? 'Estimado usuario',
                    'auto_populate_data' => true
                ]
            );
        } catch (Exception $e) {
            Log::error("Error procesando archivo con IA", [
                'error' => $e->getMessage(),
                'file_id' => $file->id
            ]);
        }
    }

    /**
     * Verifica si se debe disparar análisis comprehensivo.
     */
    private function checkForComprehensiveAnalysis(int $certificateRequestId): void
    {
        try {
            $totalFiles = FileManager::where('certificate_request_id', $certificateRequestId)
                ->where('document_type', 'ATTACHED')
                ->whereIn('extension_file', ['jpg', 'jpeg', 'png', 'pdf'])
                ->count();

            if ($totalFiles >= 2) {
                ProcessCertificateJob::dispatch(
                    '',
                    auth()->id(),
                    $certificateRequestId,
                    [
                        'comprehensive_analysis' => true,
                        'analysis_trigger' => 'file_upload',
                        'total_files' => $totalFiles
                    ]
                )->delay(now()->addSeconds(30));
            }
        } catch (Exception $e) {
            Log::error("Error verificando análisis comprehensivo", [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ]);
        }
    }

    /**
     * Obtiene la extensión de un MIME type.
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
        ];

        return $mimeMap[$mimeType] ?? 'bin';
    }
}
