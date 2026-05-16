<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Events;

use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento disparado cuando el certificado está listo para descarga del P7B (V-307).
 *
 * Sprint 4 escuchará este evento para despachar DownloadP7bJob.
 */
final class ViafirmaReadyToDownload
{
    use Dispatchable;

    public function __construct(
        public readonly ViafirmaCertificateRequest $entity,
    ) {}
}
