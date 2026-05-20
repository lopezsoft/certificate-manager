<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

/**
 * Resultado del `POST /request/fromCSR`. Wrapping limpio del JSON Viafirma.
 */
final class SubmitCsrResultDto
{
    public function __construct(
        public readonly string $codRequest,
        /** API v3.4.53: publicId siempre viene en la respuesta de POST /request/fromCSR. */
        public readonly string $publicId,
        public readonly ?string $initialStatus,
        /** @var array<string,mixed> */
        public readonly array $raw,
    ) {}
}

