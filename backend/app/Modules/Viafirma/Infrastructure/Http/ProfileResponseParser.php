<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Application\DTOs\ProfileDescriptor;

/**
 * Parsea la respuesta del endpoint `GET /ra/available-profiles?codRa={ra}`.
 *
 * Aísla la estructura del proveedor del resto del módulo (Anti-Corruption Layer).
 */
final class ProfileResponseParser
{
    /**
     * @param array<int,array<string,mixed>>|array<string,mixed> $payload
     * @return ProfileDescriptor[]
     */
    public function parse(array $payload): array
    {
        // Algunas variantes del API envuelven el listado bajo "profiles".
        $items = $payload['profiles'] ?? $payload;
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $codProfile = (string) ($item['codProfile'] ?? $item['code'] ?? '');
            if ($codProfile === '') {
                continue;
            }
            $out[] = new ProfileDescriptor(
                codProfile: $codProfile,
                name:       (string) ($item['name'] ?? $item['profileName'] ?? ''),
                dnPattern:  (string) ($item['dnPattern'] ?? ''),
                validity:   (int) ($item['validity'] ?? 730),
                token:      (string) ($item['token'] ?? 'P7B'),
                raw:        $item,
            );
        }
        return $out;
    }
}

