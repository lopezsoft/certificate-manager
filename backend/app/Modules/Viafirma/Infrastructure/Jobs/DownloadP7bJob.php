<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Models\FileManager;

/**
 * Descarga el P7B emitido por Viafirma y lo persiste en storage (V-402).
 *
 * Se despacha cuando el estado remoto llega a `Generated_Not_Downloaded`.
 * Tras descarga exitosa, despacha AssembleP12Job.
 */
final class DownloadP7bJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $requestId,
    ) {}

    public function uniqueId(): string
    {
        return "viafirma-download-{$this->requestId}";
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    /** @return string[] */
    public function tags(): array
    {
        return ["viafirma:download:{$this->requestId}"];
    }

    public function handle(
        ViafirmaClient $client,
        SafePemLogger $logger,
    ): void {
        $entity = ViafirmaCertificateRequest::with('state')->find($this->requestId);

        if ($entity === null) {
            $logger->warning('viafirma.download.entity_not_found', ['id' => $this->requestId]);
            return;
        }

        // Guard: solo descargar si está en READY_TO_DOWNLOAD
        if ($entity->internal_state !== InternalState::READY_TO_DOWNLOAD) {
            $logger->info('viafirma.download.skip_wrong_state', [
                'id'    => $entity->id,
                'state' => $entity->internal_state->value,
            ]);
            return;
        }

        // Guard: API v3.4.53 usa publicId para descargar el P7B
        if (empty($entity->public_id)) {
            $logger->warning('viafirma.download.no_public_id', ['id' => $entity->id]);
            return;
        }

        $logger->info('viafirma.download.start', ['id' => $entity->id, 'publicId' => $entity->public_id]);

        try {
            // API v3.4.53: downloadCertificateServlet?req={publicId}
            $p7bBinary = $client->downloadP7b($entity->public_id);
            
            // Obtener el código de revocación cuando el trámite finaliza su ciclo de vida remoto
            $revocationCode = $client->getRevocationCode($entity->cod_request);
        } catch (TransientHttpException $e) {
            $logger->warning('viafirma.download.transient_error', [
                'id'      => $entity->id,
                'message' => $e->getMessage(),
            ]);
            $this->release(60); // Reintentar en 1 min
            return;
        }

        // Guardar en storage bajo base_path centralizado
        $resolver = app(\App\Services\Certificates\CertificateStoragePathResolver::class);
        $disk     = $resolver->disk();
        
        $basePath = $entity->certificateRequest->base_path;
        if (empty($basePath)) {
            throw new \RuntimeException(
                "El base_path de la solicitud de certificado {$entity->certificate_request_id} no está configurado."
            );
        }

        $filename = $basePath . '/' . "{$entity->certificate_request_id}_{$entity->cod_request}.p7b";

        Storage::disk($disk)->put($filename, $p7bBinary);

        $state = $entity->state;
        if ($state) {
            $state->p7b_storage_path = $filename;
            $state->internal_state   = InternalState::DOWNLOADED;
            $state->downloaded_at    = now();
            $state->revocation_request_code = $revocationCode;
            $state->save();
        }

        // Registrar en change_histories: trámite sigue en PROCESSING (descarga lista)
        ChangeHistory::create([
            'certificate_request_id' => $entity->certificate_request_id,
            'status'                 => CertificateRequestStatusEnum::PROCESSING->value,
            'comments'               => 'Certificado recibido del proveedor — preparando archivo final.',
            'user_of_change'         => 'SYSTEM',
            'user_id'                => null,
        ]);

        // ── Registrar P7B en file_managers ────────────────────────────────────────────
        $p7bSize = strlen($p7bBinary);
        FileManager::updateOrCreate(
            [
                'certificate_request_id' => $entity->certificate_request_id,
                'file_path' => $filename,
            ],
            [
                'file_name' => basename($filename),
                'extension_file' => 'p7b',
                'mime_type' => 'application/pkcs7-mime',
                'document_type' => 'P7B_CERTIFICATE',
                'file_size' => $p7bSize,
                'status' => 'COMPLETED',
            ]
        );

        $logger->info('viafirma.download.success', [
            'id'         => $entity->id,
            'p7b_path'   => $filename,
            'size_bytes' => $p7bSize,
        ]);

        // Despachar ensamblaje P12
        AssembleP12Job::dispatch($entity->id)->delay(now()->addSeconds(5));
    }
}
