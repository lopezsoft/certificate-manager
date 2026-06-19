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
        if ($privateKeyPem === '' || $p7bDer === '') {
            throw new CryptoException('Material criptográfico vacío para ensamblaje P12.');
        }

        if ($exportPassword === '') {
            throw new CryptoException('El PIN de exportación del P12 no puede ser vacío.');
        }

        // Extraer certificados del P7B (PKCS#7 / CMS bundle)
        $certs = $this->extractCertsFromP7b($p7bDer);

        if (empty($certs)) {
            throw new CryptoException('No se encontraron certificados en el bundle P7B.');
        }

        // Separar certificado de entidad final (EE) de la cadena CA
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new CryptoException(
                'No se pudo cargar la llave privada: ' . $this->collectOpenSslErrors()
            );
        }

        $endEntityCert = null;
        $caChain       = [];

        foreach ($certs as $certPem) {
            $certResource = @openssl_x509_read($certPem);
            if ($certResource === false) {
                continue;
            }

            // Verificar si la llave privada corresponde a este certificado
            if ($endEntityCert === null && @openssl_x509_check_private_key($certResource, $privateKey)) {
                $endEntityCert = $certResource;
            } else {
                $caChain[] = $certResource;
            }
        }

        if ($endEntityCert === null) {
            throw new CryptoException(
                'No se encontró un certificado que corresponda a la llave privada proporcionada.'
            );
        }

        // Ensamblar PKCS#12
        $p12Binary = '';
        $args = [
            'friendly_name' => $friendlyName,
        ];

        $result = openssl_pkcs12_export(
            certificate: $endEntityCert,
            output:      $p12Binary,
            private_key: $privateKey,
            passphrase:  $exportPassword,
            options:     empty($caChain) ? $args : array_merge($args, ['extracerts' => $caChain]),
        );

        if (!$result || $p12Binary === '') {
            throw new CryptoException(
                'openssl_pkcs12_export falló: ' . $this->collectOpenSslErrors()
            );
        }

        return $p12Binary;
    }

    /**
     * Extrae los certificados PEM de un bundle P7B (DER o PEM).
     *
     * @return string[] Array de certificados en formato PEM.
     */
    private function extractCertsFromP7b(string $p7bData): array
    {
        // Intentar como PEM primero
        if (str_contains($p7bData, '-----BEGIN')) {
            return $this->extractCertsFromPemP7b($p7bData);
        }

        // Convertir DER a PEM para poder procesarlo
        $pemP7b = "-----BEGIN PKCS7-----\n"
            . chunk_split(base64_encode($p7bData), 64, "\n")
            . "-----END PKCS7-----\n";

        return $this->extractCertsFromPemP7b($pemP7b);
    }

    /**
     * @return string[]
     */
    private function extractCertsFromPemP7b(string $pemP7b): array
    {
        $certs = [];
        $parsed = @openssl_pkcs7_read($pemP7b, $certs);

        if ($parsed === false || empty($certs)) {
            // Fallback: el P7B podría ser un certificado suelto PEM o múltiples concatenados
            if (preg_match_all(
                '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
                $pemP7b,
                $matches,
            )) {
                return $matches[0];
            }

            throw new CryptoException(
                'No se pudo parsear el bundle P7B/PKCS7: ' . $this->collectOpenSslErrors()
            );
        }

        return $certs;
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

