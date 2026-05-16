<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

/**
 * Resultado de la construcción de un CSR PKCS#10.
 */
final class CsrResult
{
    public function __construct(
        /** CSR en formato PEM (con cabeceras BEGIN/END CERTIFICATE REQUEST). */
        public readonly string $pem,
        /** CSR codificado base64 estándar (sin cabeceras) — listo para el payload Viafirma. */
        public readonly string $base64,
        /** SHA-256 hex del CSR PEM (auditoría / detección de duplicados). */
        public readonly string $fingerprint,
    ) {}
}

