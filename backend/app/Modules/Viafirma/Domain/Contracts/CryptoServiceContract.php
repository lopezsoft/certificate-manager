<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Contracts;

use App\Modules\Viafirma\Application\DTOs\KeyPair;

/**
 * Servicio criptográfico (generación de pares RSA y ensamblaje P12).
 *
 * Las implementaciones DEBEN encerrar los `openssl_*` quirks y exponer una API
 * de dominio limpia, testeable y reemplazable (p. ej. por phpseclib3).
 */
interface CryptoServiceContract
{
    /**
     * Genera un par RSA. La llave privada queda en el resultado en PEM.
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\CryptoException
     */
    public function generateKeyPair(int $bits = 2048): KeyPair;

    /**
     * SHA-256 hex de un blob (auditoría, detección de duplicados).
     */
    public function sha256Hex(string $material): string;

    /**
     * Ensambla un archivo PKCS#12 (.p12) uniendo la llave privada con el .p7b
     * descargado de la CA.
     *
     * @param string $privateKeyPem  PEM de la llave privada (texto plano EN MEMORIA).
     * @param string $p7bDer         Contenido binario .p7b (DER).
     * @param string $friendlyName   Nombre amigable (alias) embebido en el .p12.
     * @param string $exportPassword PIN para abrir el .p12.
     *
     * @return string Binario PKCS#12.
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\CryptoException
     */
    public function assembleP12(
        string $privateKeyPem,
        string $p7bDer,
        string $friendlyName,
        string $exportPassword
    ): string;

    /**
     * Extrae el `serialNumber` del subject del certificado de entidad final
     * dentro del P7B — el número de documento del titular real al que la CA
     * emitió el certificado. Permite validar que coincida con el titular
     * originalmente solicitado antes de entregar el P12.
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\CryptoException
     */
    public function extractSubjectIdentity(string $privateKeyPem, string $p7bDer): ?string;

    /**
     * Extrae el `serialNumber` del subject de la CSR original — para comparar
     * contra {@see extractSubjectIdentity()} y detectar si la CA emitió el
     * certificado para un titular distinto al solicitado.
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\CryptoException
     */
    public function extractCsrSubjectIdentity(string $csrPem): ?string;
}

