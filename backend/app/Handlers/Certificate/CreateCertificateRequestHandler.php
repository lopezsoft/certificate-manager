<?php

namespace App\Handlers\Certificate;

use App\Commands\Certificate\CreateCertificateRequestCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Common\VerificationDigit;
use App\Enums\CertificateRequestStatusEnum;
use App\Events\CertificateRequestCreated;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Models\Company;
use App\Models\FileManager;
use App\Jobs\Certificate\AutoIssueViafirmaJob;
use App\Notifications\CertificateRequestCreateNotification;
use App\Services\Base64DecoderService;
use App\Services\QuotaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Handler para crear una nueva solicitud de certificado.
 *
 * Aplica Command Pattern: recibe CreateCertificateRequestCommand,
 * ejecuta la lógica de validación de archivos, creación de la carpeta,
 * generación del Excel, persistencia y despacho de eventos/notificaciones.
 */
class CreateCertificateRequestHandler
{
    public function __construct(
        private readonly QuotaService $quotaService,
    ) {}

    public function handle(CreateCertificateRequestCommand $command): JsonResponse
    {
        try {
            // Resolver el proveedor de emisión de la empresa
            $company  = Company::find($command->companyId);
            $provider = $company?->issuance_provider
                ?? config('certificate.issuance.default_provider', 'viafirma');
            $requiresFiles = $provider !== 'viafirma';

            // Solo validar archivos si el proveedor los requiere (mail)
            if ($requiresFiles) {
                $this->validateFiles($command);
            }

            $dv = VerificationDigit::getDigit($command->dni);

            $this->assertNoDuplicateActive($command->companyId, $command->dni, $dv);

            // Validar que la empresa tenga cupo disponible para la vigencia solicitada
            if (! $this->quotaService->hasAvailableQuotaForVigencia($command->companyId, $command->life)) {
                return HttpResponseMessages::getResponse402([
                    'message' => "No tiene certificados disponibles para vigencia de {$command->life} año(s). Debe adquirir un paquete de certificados para continuar.",
                ]);
            }

            $diskName    = config('certificate.storage.disk', 'local');
            $disk        = Storage::disk($diskName);
            $folderName  = $this->buildFolderName($command->companyId, $command->dni, $dv);

            DB::beginTransaction();

            $initialStatus = $provider === 'viafirma' 
                ? CertificateRequestStatusEnum::PROCESSING->value 
                : CertificateRequestStatusEnum::DRAFT->value;

            $attributes = [
                'company_id'               => $command->companyId,
                'country_id'               => $command->countryId,
                'city_id'                  => $command->cityId,
                'identity_document_id'     => $command->identityDocumentId,
                'type_organization_id'     => $command->typeOrganizationId,
                'document_number'          => strip_tags($command->documentNumber),
                'address'                  => strip_tags($command->address),
                'legal_representative'     => $command->legalRepresentative ? Str::upper(strip_tags($command->legalRepresentative)) : null,
                'legal_rep_first_name'     => $command->legalRepFirstName ? Str::upper(strip_tags($command->legalRepFirstName)) : null,
                'legal_rep_last_name'      => $command->legalRepLastName ? Str::upper(strip_tags($command->legalRepLastName)) : null,
                'legal_rep_email'          => $command->legalRepEmail,
                'company_name'             => Str::upper(strip_tags($command->companyName)),
                'dni'                      => strip_tags($command->dni),
                'dv'                       => $dv,
                'info'                     => strip_tags($command->info ?? ''),
                'life'                     => $command->life,
                'mobile'                   => $command->mobile ? strip_tags($command->mobile) : null,
                'phone'                    => $command->phone ? strip_tags($command->phone) : null,
                'base_path'                => $folderName,
                'request_status'           => $initialStatus,
            ];

            // entity_document_type_id: la columna es NOT NULL con DEFAULT en BD.
            // Se omite la clave (en vez de forzar null) cuando no aplica (Persona
            // Natural), dejando que el default de BD aplique — inofensivo, ya que
            // el campo no participa en ninguna decisión de negocio para PN.
            // Para Persona Jurídica, CreateCertificateRequestFormRequest ya
            // garantizó que el valor está presente y es válido.
            if ($command->entityDocumentTypeId !== null) {
                $attributes['entity_document_type_id'] = $command->entityDocumentTypeId;
            }

            $certificate = CertificateRequest::create($attributes);

            ChangeHistory::create([
                'certificate_request_id' => $certificate->id,
                'status'                 => $initialStatus,
                'comments'               => 'Solicitud de certificado creada',
                'user_of_change'         => 'USER',
                'user_id'                => $command->userId,
            ]);

            // Excel solo para flujo mail
            if ($requiresFiles) {
                $certificate->load(['city', 'identity']);

                [$reader, $activeSheet] = $this->loadExcelTemplate();
                $this->fillAndStoreExcel($activeSheet, $certificate, $command->dni, $dv, $folderName, $disk);
            }

            // Guardar adjuntos SIEMPRE que estén presentes, independiente del proveedor
            if (!empty($command->attachments)) {
                $this->storeUploadedFiles($command->attachments, $folderName, $disk, $certificate->id);
            }

            // Consumir un cupo (POSTPAID o PREPAID) de forma atómica, validando vigencia
            $itemId = $this->quotaService->consumeQuotaForVigencia($command->companyId, $command->life);

            // Vincular el item PREPAID consumido con el certificado (si aplica)
            if ($itemId !== null) {
                DB::table('certificate_order_items')
                    ->where('id', $itemId)
                    ->update(['certificate_request_id' => $certificate->id]);
            }

            DB::commit();

            // Cargar relaciones para la respuesta y el evento
            $certificate->load(['files']);

            event(new CertificateRequestCreated($certificate));

            Notification::route('mail', config('certificate.mail.support_address'))
                ->notify(new CertificateRequestCreateNotification($certificate));

            if ($provider === 'viafirma') {
                AutoIssueViafirmaJob::dispatch($certificate->id, $command->userId);
            }

            return HttpResponseMessages::getResponse([
                'message'     => 'Solicitud de certificado creada exitosamente',
                'dataRecords' => $certificate,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            // Si la cuota ya fue consumida pero la transacción falló, devolverla
            try {
                $this->quotaService->releaseQuota($command->companyId);
            } catch (Exception) {
                // No hacer nada si el release falla; el consumo pudo no haberse ejecutado
            }

            return MessageExceptionResponse::response($e);
        }
    }

    // ── Validaciones ──────────────────────────────────────────────────────────

    /**
     * Valida adjuntos Base64 (ya validados en Form Request, pero se mantiene para seguridad).
     * Esta validación es redundante pero proporciona una capa adicional de seguridad.
     */
    private function validateFiles(CreateCertificateRequestCommand $command): void
    {
        // La validación principal ocurre en CreateCertificateRequestFormRequest
        // Esta función se mantiene como capa adicional de seguridad
        if (empty($command->attachments)) {
            return;
        }

        $maxFiles = config('certificate.file_upload.max_files', 3);
        $minFiles = config('certificate.file_upload.min_files', 2);

        if (count($command->attachments) > $maxFiles) {
            throw new Exception("El número de archivos supera los {$maxFiles} soportados.", 400);
        }
        if (count($command->attachments) < $minFiles) {
            throw new Exception("Debe enviar al menos {$minFiles} archivos.", 400);
        }
    }

    private function assertNoDuplicateActive(int $companyId, string $dni, int $dv): void
    {
        $exists = CertificateRequest::query()
            ->where('company_id', $companyId)
            ->where('dni', $dni)
            ->where('dv', $dv)
            ->whereIn('request_status', CertificateRequestStatusEnum::activeStatuses())
            ->exists();

        if ($exists) {
            throw new Exception(
                'Ya existe una solicitud de certificado, en proceso, con el mismo NIT y DV. '
                . 'Por favor verifique el estado de la solicitud.',
                400
            );
        }
    }

    // ── Helpers de Storage ────────────────────────────────────────────────────

    private function buildFolderName(int $companyId, string $dni, int $dv): string
    {
        $mainPath = config('certificate.storage.main_path');
        return sprintf('%s/%d/%s/%s/%s%d', $mainPath, $companyId, date('Y'), date('m'), $dni, $dv);
    }

    private function loadExcelTemplate(): array
    {
        $local      = Storage::disk('local');
        $fileXls    = $local->path('templates/template-data-certificate.xlsx');
        $reader     = new Xlsx();
        $spreadsheet = $reader->load($fileXls);
        $spreadsheet->setActiveSheetIndex(0);

        return [$reader, $spreadsheet->getActiveSheet()];
    }

    private function fillAndStoreExcel($activeSheet, CertificateRequest $certificate, string $dni, int $dv, string $folderName, $disk): void
    {
        $activeSheet->setCellValue('C4',  $certificate->company_name);
        $activeSheet->setCellValue('C5',  "{$dni}-{$dv}");
        $activeSheet->setCellValue('C6',  $certificate->address);
        $activeSheet->setCellValue('C7',  $certificate->city->name_city);
        $activeSheet->setCellValue('C8',  $certificate->legal_representative);
        $activeSheet->setCellValue('C9',  $certificate->identity->document_name);
        $activeSheet->setCellValue('C10', $certificate->document_number);
        $activeSheet->setCellValue('C11', config('certificate.mail.support_address'));
        $activeSheet->setCellValue('C12', $certificate->phone);
        $activeSheet->setCellValue('C13', $certificate->mobile);
        $activeSheet->setCellValue('C14', "{$certificate->life} año(s)");
        $activeSheet->setCellValue('C15', 'Factura electronica');

        $tmpFile  = Str::uuid() . '.xlsx';
        $writer   = IOFactory::createWriter($activeSheet->getParent(), 'Xlsx');
        $writer->save("storage/{$tmpFile}");

        $content  = Storage::disk('public')->get($tmpFile);
        $path     = "{$folderName}/EXCEL-DATOS-CERTIFICADO-{$dni}{$dv}.xlsx";
        $disk->put($path, $content);
        Storage::disk('public')->delete($tmpFile);

        FileManager::create([
            'certificate_request_id' => $certificate->id,
            'file_name'              => "EXCEL-DATOS-CERTIFICADO-{$dni}{$dv}.xlsx",
            'file_path'              => $path,
            'extension_file'         => pathinfo($path, PATHINFO_EXTENSION),
            'mime_type'              => $disk->mimeType($path),
            'file_size'              => $disk->size($path),
            'last_modified'          => date('Y-m-d H:i:s', $disk->lastModified($path)),
            'status'                 => 'COMPLETED',
            'document_type'          => 'ATTACHED',
        ]);
    }

    /**
     * Procesa y almacena archivos en Base64.
     * Soporta archivos con prefijo Data URI (data:application/pdf;base64,...)
     * Detecta automáticamente MIME type y nombre si no están presentes.
     */
    private function storeUploadedFiles(array $files, string $folderName, $disk, int $certificateId): void
    {
        $decoder = app(Base64DecoderService::class);

        foreach ($files as $file) {
            // Decodificar Base64 (soporta prefijo Data URI)
            try {
                $metadata = $decoder->decodeWithMetadata($file['base64']);
                $binaryContent = $metadata['binary_content'];
                $extractedMimeType = $metadata['mime_type'];
            } catch (Exception $e) {
                throw new Exception("El archivo contiene Base64 inválido", 400);
            }

            // Obtener o generar nombre del archivo
            $fileName = $file['name'] ?? null;
            if (empty($fileName)) {
                $fileName = $this->generateFileName($file['type'] ?? $extractedMimeType, $binaryContent);
            }

            // Obtener o detectar MIME type (prioridad: enviado > extraído del prefijo > detectado)
            $mimeType = $file['type'] ?? $extractedMimeType;
            if (empty($mimeType)) {
                $mimeType = $this->detectMimeType($binaryContent);
            }

            // Guardar archivo
            $path = "{$folderName}/{$fileName}";
            $disk->put($path, $binaryContent);

            // Registrar en FileManager
            FileManager::create([
                'certificate_request_id' => $certificateId,
                'file_name'              => $fileName,
                'file_path'              => $path,
                'extension_file'         => pathinfo($fileName, PATHINFO_EXTENSION),
                'mime_type'              => $mimeType,
                'file_size'              => strlen($binaryContent),
                'last_modified'          => date('Y-m-d H:i:s'),
                'status'                 => 'COMPLETED',
                'document_type'          => 'ATTACHED',
            ]);
        }
    }

    /**
     * Detecta el MIME type del contenido binario.
     */
    private function detectMimeType(string $binaryContent): string
    {
        // Usar finfo para detectar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $binaryContent);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Genera un nombre de archivo basado en el MIME type.
     */
    private function generateFileName(?string $mimeType, string $binaryContent): string
    {
        $extension = 'bin';

        if (!empty($mimeType)) {
            $extension = match ($mimeType) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                default => $this->getExtensionFromMimeType($mimeType)
            };
        } else {
            // Detectar extensión del contenido binario
            $detected = $this->detectMimeType($binaryContent);
            $extension = match ($detected) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'bin'
            };
        }

        return 'document_' . Str::uuid() . '.' . $extension;
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
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/json' => 'json',
        ];

        return $mimeMap[$mimeType] ?? 'bin';
    }
}
