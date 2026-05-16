<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Events;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento disparado cuando la FSM detecta un cambio de InternalState (V-307).
 */
final class ViafirmaStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly ViafirmaCertificateRequest $entity,
        public readonly InternalState $previousState,
        public readonly InternalState $newState,
        public readonly RemoteStatus $remoteStatus,
    ) {}
}
