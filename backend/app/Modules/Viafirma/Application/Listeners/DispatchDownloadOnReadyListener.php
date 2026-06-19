<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Listeners;

use App\Modules\Viafirma\Domain\Events\ViafirmaReadyToDownload;
use App\Modules\Viafirma\Infrastructure\Jobs\DownloadP7bJob;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Escucha ViafirmaReadyToDownload y despacha el DownloadP7bJob (V-408).
 *
 * Este es el puente entre el evento de dominio del Sprint 3 y el
 * pipeline de descarga/ensamblaje del Sprint 4.
 */
final class DispatchDownloadOnReadyListener
{
    public function __construct(
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(ViafirmaReadyToDownload $event): void
    {
        $entity = $event->entity;

        $this->logger->info('viafirma.listener.ready_to_download', [
            'id'  => $entity->id,
            'cod' => $entity->cod_request,
        ]);

        DownloadP7bJob::dispatch($entity->id)->delay(now()->addSeconds(10));
    }
}
