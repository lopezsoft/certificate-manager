<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

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
use Psr\Log\LoggerInterface;

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
        LoggerInterface $logger,
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

        if (empty($entity->cod_request)) {
            $logger->warning('viafirma.download.no_cod_request', ['id' => $entity->id]);
            return;
        }

        $logger->info('viafirma.download.start', ['id' => $entity->id, 'cod' => $entity->cod_request]);

        try {
            $p7bBinary = $client->downloadP7b($entity->cod_request);
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

        $logger->info('viafirma.download.success', [
            'id'         => $entity->id,
            'p7b_path'   => $filename,
            'size_bytes' => strlen($p7bBinary),
        ]);

        // Despachar ensamblaje P12
        AssembleP12Job::dispatch($entity->id)->delay(now()->addSeconds(5));
    }
}
