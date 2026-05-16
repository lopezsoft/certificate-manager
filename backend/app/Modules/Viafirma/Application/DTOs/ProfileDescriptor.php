<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

/**
 * Descriptor de un perfil retornado por `GET /ra/available-profiles?codRa={ra}`.
 */
final class ProfileDescriptor
{
    public function __construct(
        public readonly string $codProfile,
        public readonly string $name,
        public readonly string $dnPattern,
        public readonly int $validity,
        public readonly string $token,
        public readonly array $raw,
    ) {}
}

