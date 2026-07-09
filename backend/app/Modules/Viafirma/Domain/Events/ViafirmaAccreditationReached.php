<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Events;

use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento disparado cuando la solicitud entra en estado remoto `accreditation` (KYC pending).
 *
 * Se dispara incondicionalmente al cambio de remote_status → ACCREDITATION,
 * independientemente de si el internal_state cambió (permite capturar
 * transiciones como rues_check→accreditation, ambas en POLLING).
 */
final class ViafirmaAccreditationReached
{
    use Dispatchable;

    public function __construct(
        public readonly ViafirmaCertificateRequest $entity,
    ) {}
}
