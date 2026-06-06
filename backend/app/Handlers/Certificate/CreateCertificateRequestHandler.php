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
use App\Models\FileManager;
use App\Notifications\CertificateRequestCreateNotification;
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
            $this->validateFiles($command);

            $dv = VerificationDigit::getDigit($command->dni);

            $this->assertNoDuplicateActive($command->companyId, $command->dni, $dv);

            // Validar que la empresa tenga cupo disponible antes de proceder
            if (! $this->quotaService->hasAvailableQuota($command->companyId)) {
                return HttpResponseMessages::getResponse402([
                    'message' => 'No tiene certificados disponibles. Debe adquirir un paquete de certificados para continuar.',
                ]);
            }

            $disk        = Storage::disk('attachment');
            $folderName  = $this->buildFolderName($command->companyId, $command->dni, $dv);
            $disk->makeDirectory($folderName);

            [$reader, $activeSheet] = $this->loadExcelTemplate();

            DB::beginTransaction();

            $certificate = CertificateRequest::create([
                'company_id'            => $command->companyId,
                'city_id'               => $command->cityId,
                'identity_document_id'  => $command->identityDocumentId,
                'type_organization_id'  => $command->typeOrganizationId,
                'document_number'       => strip_tags($command->documentNumber),
                'address'               => strip_tags($command->address),
                'legal_representative'  => Str::upper(strip_tags($command->legalRepresentative)),
                'company_name'          => Str::upper(strip_tags($command->companyName)),
                'dni'                   => strip_tags($command->dni),
                'dv'                    => $dv,
                'info'                  => strip_tags($command->info ?? ''),
                'life'                  => $command->life,
                'base_path'             => $folderName,
            ]);

            ChangeHistory::create([
                'certificate_request_id' => $certificate->id,
                'status'                 => CertificateRequestStatusEnum::DRAFT->value,
                'comments'               => 'Solicitud de certificado creada',
                'user_of_change'         => 'USER',
                'user_id'                => $command->userId,
            ]);

            // Cargar relaciones necesarias para fillAndStoreExcel
            $certificate->load(['city', 'identity']);

            $this->fillAndStoreExcel($activeSheet, $certificate, $command->dni, $dv, $folderName, $disk);
            $this->storeUploadedFiles($command->files, $folderName, $disk, $certificate->id);

            // Consumir un cupo (POSTPAID o PREPAID) de forma atómica
            $this->quotaService->consumeQuota($command->companyId);

            DB::commit();

            // Cargar relaciones para la respuesta y el evento
            $certificate->load(['files']);

            event(new CertificateRequestCreated($certificate));

            Notification::route('mail', config('certificate.mail.support_address'))
                ->notify(new CertificateRequestCreateNotification($certificate));

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

    private function validateFiles(CreateCertificateRequestCommand $command): void
    {
        $maxFiles         = config('certificate.file_upload.max_files', 3);
        $minFiles         = config('certificate.file_upload.min_files', 2);
        $maxFileSize      = config('certificate.file_upload.max_file_size', 7);
        $maxTotalSize     = config('certificate.file_upload.max_total_size', 10);
        $maxFileSizeBytes = $maxFileSize * 1024 * 1024;
        $maxTotalBytes    = $maxTotalSize * 1024 * 1024;

        if (count($command->files) > $maxFiles) {
            throw new Exception("El número de archivos adjuntos supera los {$maxFiles} soportados.", 400);
        }
        if (count($command->files) < $minFiles) {
            throw new Exception("Debe enviar al menos {$minFiles} archivos adjuntos.", 400);
        }

        $total = 0;
        foreach ($command->files as $file) {
            $size = $file->getSize();
            if ($size > $maxFileSizeBytes) {
                $mb = round($size / 1024 / 1024, 2);
                throw new Exception("El archivo '{$file->getClientOriginalName()}' supera el tamaño máximo de {$maxFileSize} MB (tamaño: {$mb} MB).", 400);
            }
            $total += $size;
        }

        if ($total > $maxTotalBytes) {
            $mb = round($total / 1024 / 1024, 2);
            throw new Exception("El tamaño total de los archivos supera los {$maxTotalSize} MB permitidos. Total: {$mb} MB.", 400);
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
        return sprintf('companies/%d/%s/%s/%s%d', $companyId, date('Y'), date('m'), $dni, $dv);
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

    private function storeUploadedFiles(array $files, string $folderName, $disk, int $certificateId): void
    {
        foreach ($files as $file) {
            $fileName = $file->getClientOriginalName();
            $path     = "{$folderName}/{$fileName}";
            $disk->putFileAs($folderName, $file, $fileName);

            FileManager::create([
                'certificate_request_id' => $certificateId,
                'file_name'              => $fileName,
                'file_path'              => $path,
                'extension_file'         => pathinfo($path, PATHINFO_EXTENSION),
                'mime_type'              => $disk->mimeType($path),
                'file_size'              => $disk->size($path),
                'last_modified'          => date('Y-m-d H:i:s', $disk->lastModified($path)),
                'status'                 => 'COMPLETED',
                'document_type'          => 'ATTACHED',
            ]);
        }
    }
}
