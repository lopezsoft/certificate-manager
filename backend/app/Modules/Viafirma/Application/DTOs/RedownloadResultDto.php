<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

/**
 * Resultado de la operación de re-descarga y regeneración de P12 para admin.
 */
final class RedownloadResultDto
{
    public function __construct(
        public readonly string $pin,
        public readonly string $downloadUrl,
        public readonly int    $viafirmaId,
        public readonly string $internalState,
        public readonly string $remoteStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'pin'            => $this->pin,
            'download_url'   => $this->downloadUrl,
            'expires_at'     => null,
            'viafirma_id'    => $this->viafirmaId,
            'internal_state' => $this->internalState,
            'remote_status'  => $this->remoteStatus,
        ];
    }
}
