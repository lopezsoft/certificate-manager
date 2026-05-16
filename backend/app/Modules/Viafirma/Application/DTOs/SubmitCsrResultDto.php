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
        public readonly ?string $publicId,
        public readonly ?string $initialStatus,
        /** @var array<string,mixed> */
        public readonly array $raw,
    ) {}
}

