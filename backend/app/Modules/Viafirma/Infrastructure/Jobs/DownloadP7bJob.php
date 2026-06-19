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
        $entity = ViafirmaCertificateRequest::find($this->requestId);

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
        } catch (TransientHttpException $e) {
            $logger->warning('viafirma.download.transient_error', [
                'id'      => $entity->id,
                'message' => $e->getMessage(),
            ]);
            $this->release(60); // Reintentar en 1 min
            return;
        }

        // Guardar en storage
        $disk = config('viafirma.storage.p7b_disk', 'local');
        $path = config('viafirma.storage.p7b_path', 'viafirma/p7b');
        $filename = "{$path}/{$entity->cod_request}.p7b";

        Storage::disk($disk)->put($filename, $p7bBinary);

        $entity->p7b_storage_path = $filename;
        $entity->internal_state   = InternalState::DOWNLOADED;
        $entity->downloaded_at    = now();
        $entity->save();

        // Registrar en change_histories: trámite sigue en PROCESSING (descarga lista)
        ChangeHistory::create([
            'certificate_request_id' => $entity->certificate_request_id,
            'status'                 => CertificateRequestStatusEnum::PROCESSING->value,
            'comments'               => 'Certificado recibido del proveedor — preparando archivo final.',
            'user_of_change'         => 'SYSTEM',
            'user_id'                => null,
        ]);

        $logger->info('viafirma.download.success', [
            'id'         => $entity->id,
            'p7b_path'   => $filename,
            'size_bytes' => strlen($p7bBinary),
        ]);

        // Despachar ensamblaje P12
        AssembleP12Job::dispatch($entity->id)->delay(now()->addSeconds(5));
    }
}
