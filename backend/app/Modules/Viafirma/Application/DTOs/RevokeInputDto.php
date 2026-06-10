<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

use App\Modules\Viafirma\Domain\Enums\RevocationReason;

/**
 * DTO inmutable que encapsula los parámetros necesarios para revocar un certificado.
 */
final class RevokeInputDto
{
    public function __construct(
        public readonly int $viafirmaCertificateRequestId,
        public readonly string $revokingCode,
        public readonly RevocationReason $revocationReason,
        public readonly int $revokedByUserId,
    ) {}
}
