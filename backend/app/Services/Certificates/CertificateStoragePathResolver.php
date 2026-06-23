<?php

declare(strict_types=1);

namespace App\Services\Certificates;

/**
 * Resolver único del ruteo de almacenamiento de certificados (agnóstico de proveedor).
 *
 * Compone rutas con el formato:
 *   {prefix}/certificates/{sub}/{filename}
 *
 * donde {sub} se define por proveedor/artefacto en config/certificate.php
 * (clave "{provider}_{artifact}", p.ej. "viafirma_p12"). Centraliza la lógica
 * para que jobs y use-cases no repitan literales (SOLID: una sola responsabilidad).
 */
final class CertificateStoragePathResolver
{
    /** Disco de Laravel configurado para certificados (s3 | local | ...). */
    public function disk(): string
    {
        return (string) config('certificate.storage.disk', 'local');
    }

    /** Disco del proveedor legacy (otro proveedor). Default histórico: 'attachment'. */
    public function legacyDisk(): string
    {
        return (string) config('certificate.storage.legacy_disk', 'attachment');
    }

    /** Directorio (sin nombre de archivo) para un proveedor/artefacto. */
    public function directory(string $provider, string $artifact): string
    {
        $prefix = trim((string) config('certificate.storage.prefix', 'local'), '/');
        $sub    = trim((string) config(
            "certificate.storage.paths.{$provider}_{$artifact}",
            "{$provider}/{$artifact}",
        ), '/');

        $segments = array_filter([$prefix, 'certificates', $sub], static fn ($s) => $s !== '');

        return implode('/', $segments);
    }

    /** Ruta completa (directorio + nombre de archivo) para un artefacto. */
    public function path(string $provider, string $artifact, string $filename): string
    {
        return $this->directory($provider, $artifact) . '/' . ltrim($filename, '/');
    }
}
