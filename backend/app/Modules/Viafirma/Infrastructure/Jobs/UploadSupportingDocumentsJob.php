<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Models\FileManager;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Sube al RA los documentos de soporte ya adjuntos a la solicitud — manual RA
 * §2.3.7 "Anexar documentos a una solicitud" (POST /files/upload/).
 *
 * Requerido para organizaciones sin RUES (entity_document_type_id = 99): su
 * verificación automática no puede completarse (no hay registro contra el
 * cual validar), por lo que Viafirma requiere adjuntar el documento que
 * acredite la representación legal (acta de constitución, nombramiento de
 * administrador, etc.) para revisión manual del operador RA.
 *
 * Se despacha una sola vez justo después de someter el CSR
 * (IssueCertificateUseCase). Reutiliza los archivos ya adjuntos por el
 * cliente vía POST /certificate-request/{id}/files (document_type=ATTACHED)
 * — no crea un flujo de carga nuevo.
 */
final class UploadSupportingDocumentsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $requestId,
    ) {
        $this->onQueue('viafirma-poll');
    }

    public function uniqueId(): string
    {
        return "viafirma-upload-docs-{$this->requestId}";
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    /** @return string[] */
    public function tags(): array
    {
        return ["viafirma:upload-docs:{$this->requestId}"];
    }

    public function handle(ViafirmaClient $client, SafePemLogger $logger): void
    {
        $entity = ViafirmaCertificateRequest::find($this->requestId);

        if ($entity === null) {
            $logger->warning('viafirma.upload_docs.entity_not_found', ['id' => $this->requestId]);
            return;
        }

        if (empty($entity->cod_request)) {
            $logger->warning('viafirma.upload_docs.no_cod_request', ['id' => $entity->id]);
            return;
        }

        $attachments = FileManager::where('certificate_request_id', $entity->certificate_request_id)
            ->where('document_type', 'ATTACHED')
            ->get();

        if ($attachments->isEmpty()) {
            $logger->warning('viafirma.upload_docs.no_attachments', [
                'id'                     => $entity->id,
                'certificate_request_id' => $entity->certificate_request_id,
            ]);
            return;
        }

        // Idempotencia: no volver a subir archivos que ya están en Viafirma
        // (mismo nombre) — evita duplicados en reintentos del job.
        try {
            $alreadyUploaded = array_column($client->listFiles($entity->cod_request), null, 'name');
        } catch (\Throwable $e) {
            $logger->warning('viafirma.upload_docs.list_failed', [
                'id'    => $entity->id,
                'error' => $e->getMessage(),
            ]);
            $alreadyUploaded = [];
        }

        $disk  = Storage::disk(config('certificate.storage.disk'));
        $files = [];

        foreach ($attachments as $attachment) {
            $fileName = $attachment->file_name;

            if (isset($alreadyUploaded[$fileName])) {
                continue;
            }

            if (!$disk->exists($attachment->file_path)) {
                $logger->warning('viafirma.upload_docs.file_missing', [
                    'id'   => $entity->id,
                    'path' => $attachment->file_path,
                ]);
                continue;
            }

            $files[] = [
                'name'   => $fileName,
                'base64' => base64_encode($disk->get($attachment->file_path)),
            ];
        }

        if (empty($files)) {
            $logger->info('viafirma.upload_docs.nothing_to_upload', ['id' => $entity->id]);
            return;
        }

        try {
            $uploaded = $client->uploadFiles($entity->cod_request, $files);
        } catch (TransientHttpException $e) {
            $logger->warning('viafirma.upload_docs.transient_error', [
                'id'    => $entity->id,
                'error' => $e->getMessage(),
            ]);
            $this->release(60);
            return;
        }

        $logger->info('viafirma.upload_docs.success', [
            'id'          => $entity->id,
            'cod_request' => $entity->cod_request,
            'uploaded'    => array_column($uploaded, 'name'),
        ]);
    }
}
