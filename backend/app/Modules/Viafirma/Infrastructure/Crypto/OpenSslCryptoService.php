<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Crypto;

use App\Modules\Viafirma\Application\DTOs\KeyPair;
use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Exceptions\CryptoException;

/**
 * Implementación basada en `ext-openssl` (ya requerida en composer.json).
 *
 * NOTA arquitectónica:
 * Esta implementación es intencionalmente delgada y reemplazable por una
 * basada en phpseclib3 (ver V-103 del roadmap §6) sin afectar al dominio,
 * gracias al contrato {@see CryptoServiceContract} (DIP).
 *
 * SEGURIDAD: la llave privada NUNCA debe loguearse. Se transporta sólo el
 * tiempo necesario hasta el {@see \App\Modules\Viafirma\Domain\Contracts\KeyVault}.
 */
final class OpenSslCryptoService implements CryptoServiceContract
{
    /**
     * @param string      $digestAlg   Algoritmo de digest para firma y huellas (default: sha256).
     * @param string|null $opensslConf Ruta al openssl.cnf a usar. Si null, OpenSSL usa el del SO.
     */
    public function __construct(
        private readonly string $digestAlg = 'sha256',
        private readonly ?string $opensslConf = null,
    ) {}

    public function generateKeyPair(int $bits = 2048): KeyPair
    {
        if ($bits < 2048) {
            throw new CryptoException('Tamaño de llave RSA inválido: mínimo 2048 bits.');
        }

        $config = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => $this->digestAlg,
        ];
        if ($this->opensslConf !== null && is_file($this->opensslConf)) {
            $config['config'] = $this->opensslConf;
        }

        $res = @openssl_pkey_new($config);
        if ($res === false) {
            throw new CryptoException(
                'openssl_pkey_new falló: ' . $this->collectOpenSslErrors()
            );
        }

        $privateKeyPem = '';
        $exportOk = isset($config['config'])
            ? openssl_pkey_export($res, $privateKeyPem, null, ['config' => $config['config']])
            : openssl_pkey_export($res, $privateKeyPem);
        if (!$exportOk) {
            throw new CryptoException(
                'openssl_pkey_export falló: ' . $this->collectOpenSslErrors()
            );
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false || empty($details['key'])) {
            throw new CryptoException(
                'No se pudo extraer la llave pública: ' . $this->collectOpenSslErrors()
            );
        }

        // Vaciar la cola de errores OpenSSL (no-fatales emitidos durante el load
        // de openssl.cnf en sistemas donde no existe el del SO) para que no
        // contaminen los siguientes asserts.
        while (openssl_error_string() !== false) {
            // drain
        }

        return new KeyPair(
            publicKeyPem: $details['key'],
            privateKeyPem: $privateKeyPem,
            bits: $bits,
        );
    }

    public function sha256Hex(string $material): string
    {
        return hash('sha256', $material);
    }

    public function assembleP12(
        string $privateKeyPem,
        string $p7bDer,
        string $friendlyName,
        string $exportPassword
    ): string {
        // ⚠️ STUB de Sprint 1 — la implementación real se cierra en Sprint 4 (V-403),
        // tras validar la extracción del certificado de entidad final + cadena
        // desde el .p7b (CMS/PKCS#7). Lanzamos para evitar usos accidentales.
        throw new CryptoException(
            'assembleP12 será implementado en Sprint 4 (V-403). No invocar todavía.'
        );
    }

    private function collectOpenSslErrors(): string
    {
        $errors = [];
        while (($e = openssl_error_string()) !== false) {
            $errors[] = $e;
        }
        return $errors === [] ? '(sin detalle)' : implode(' | ', $errors);
    }
}

