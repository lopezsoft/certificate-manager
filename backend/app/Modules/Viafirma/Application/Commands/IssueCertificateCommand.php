<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Commands;

use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;

/**
 * Comando que dispara la emisión de un certificado Viafirma.
 *
 * Es el contrato entre la capa Presentación (FormRequest / Controller) y el
 * UseCase. Inmutable, tipado, sin lógica.
 *
 * El `profileType` (FE_PJ/FE_PN), `identityType` y resto de campos del CSR se
 * RESUELVEN dentro del UseCase a partir del `certificateRequestId` consultando
 * los catálogos productivos (homologación §3.0 del roadmap). Aquí sólo viajan
 * los datos que NO se pueden inferir de la BD.
 */
final class IssueCertificateCommand
{
    public function __construct(
        public readonly int $certificateRequestId,
        public readonly ?int $requestedByUserId,
        /** Email de notificación KYC (puede ser distinto al email del CSR). */
        public readonly string $emailCertificate,
        /** Sólo aplica si la empresa es PJ; debe ser null para PN. */
        public readonly ?OrganizationType $organizationType,
        /**
         * `IdentityType` del solicitante (override opcional cuando el caller
         * tiene mejor info que la BD; si null, se deriva del catálogo).
         */
        public readonly ?IdentityType $identityTypeOverride = null,
    ) {}
}

