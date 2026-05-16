<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Events;

use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento disparado cuando un trámite Viafirma falla (terminal o recoverable) (V-307).
 */
final class ViafirmaRequestFailed
{
    use Dispatchable;

    public function __construct(
        public readonly ViafirmaCertificateRequest $entity,
        public readonly string $errorCode,
        public readonly string $errorMessage,
    ) {}
}
