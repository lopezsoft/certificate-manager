<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

/**
 * Par de llaves RSA generado por el {@see \App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract}.
 *
 * ⚠️ El campo `privateKeyPem` NUNCA debe persistirse en logs ni viajar a través
 * de la red. Inmediatamente tras su generación debe almacenarse cifrado en el
 * {@see \App\Modules\Viafirma\Domain\Contracts\KeyVault}.
 */
final class KeyPair
{
    public function __construct(
        public readonly string $publicKeyPem,
        public readonly string $privateKeyPem,
        public readonly int $bits,
    ) {}
}

