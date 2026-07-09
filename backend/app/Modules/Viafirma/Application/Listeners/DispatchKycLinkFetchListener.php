<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Listeners;

use App\Modules\Viafirma\Domain\Events\ViafirmaAccreditationReached;
use App\Modules\Viafirma\Infrastructure\Jobs\FetchKycAccreditationLinkJob;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Escucha ViafirmaAccreditationReached y despacha FetchKycAccreditationLinkJob
 * para capturar el link KYC de forma automática cuando la solicitud entra
 * en estado de acreditación.
 */
final class DispatchKycLinkFetchListener
{
    public function __construct(
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(ViafirmaAccreditationReached $event): void
    {
        $entity = $event->entity;

        $this->logger->info('viafirma.listener.accreditation_reached', [
            'id'  => $entity->id,
            'cod' => $entity->cod_request,
        ]);

        FetchKycAccreditationLinkJob::dispatch($entity->id)->delay(now()->addSeconds(5));
    }
}
