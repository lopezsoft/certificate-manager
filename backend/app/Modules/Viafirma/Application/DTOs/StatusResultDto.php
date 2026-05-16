<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

use App\Modules\Viafirma\Domain\Enums\RemoteStatus;

/**
 * Resultado tipado de `GET /request/{cod}/status` (V-304).
 *
 * Encapsula el estado remoto parseado, el código de solicitud y
 * el payload crudo para auditoría en `viafirma_status_history`.
 */
final class StatusResultDto
{
    public function __construct(
        public readonly RemoteStatus $status,
        public readonly string $codRequest,
        public readonly array $raw = [],
    ) {}
}
