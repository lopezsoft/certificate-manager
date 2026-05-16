<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Contracts;

/**
 * Custodia de material criptográfico (llaves privadas, PINs).
 *
 * Patrón: DIP — las implementaciones concretas (EncryptedLocal / AWS KMS) se
 * inyectan según el entorno. Ninguna capa de aplicación debe conocer detalles
 * del backend.
 */
interface KeyVault
{
    /**
     * Guarda el material y retorna una referencia opaca (id, ARN o ruta cifrada).
     *
     * @param array<string,mixed> $metadata Metadata pública adicional (no sensible).
     */
    public function store(string $material, array $metadata = []): string;

    public function retrieve(string $ref): string;

    public function destroy(string $ref): void;

    public function exists(string $ref): bool;
}

