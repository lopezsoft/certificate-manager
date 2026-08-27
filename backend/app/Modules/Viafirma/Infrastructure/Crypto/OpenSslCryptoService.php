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

        [$endEntityCert, $caChain, $privateKey] = $this->findEndEntityCertificate($privateKeyPem, $p7bDer);

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
     * Extrae el `serialNumber` del subject del certificado de entidad final
     * emitido por la CA — el número de documento del titular real del
     * certificado, según lo que Viafirma efectivamente aprobó.
     *
     * Usado para detectar discrepancias entre el titular solicitado (CSR) y
     * el titular al que la CA terminó emitiendo el certificado (ej. error de
     * validación biométrica del lado del proveedor).
     *
     * @return string|null El `serialNumber` del subject, o null si el
     *                      certificado no expone ese campo.
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\CryptoException
     */
    public function extractSubjectIdentity(string $privateKeyPem, string $p7bDer): ?string
    {
        [$endEntityCert] = $this->findEndEntityCertificate($privateKeyPem, $p7bDer);

        $parsed = @openssl_x509_parse($endEntityCert);
        if ($parsed === false) {
            throw new CryptoException(
                'No se pudo parsear el certificado de entidad final: ' . $this->collectOpenSslErrors()
            );
        }

        return $parsed['subject']['serialNumber'] ?? null;
    }

    /**
     * Extrae el `serialNumber` del subject de la CSR original (lo que
     * nosotros solicitamos), para compararlo contra {@see extractSubjectIdentity()}
     * (lo que la CA efectivamente emitió). Comparar contra la CSR real evita
     * depender de qué campo de negocio (`dni`, `document_number`, etc.) se usó
     * al construirla — la CSR es la fuente de verdad exacta de lo enviado.
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\CryptoException
     */
    public function extractCsrSubjectIdentity(string $csrPem): ?string
    {
        $csr = @openssl_csr_get_subject($csrPem);
        if ($csr === false) {
            throw new CryptoException(
                'No se pudo parsear el subject de la CSR: ' . $this->collectOpenSslErrors()
            );
        }

        return $csr['serialNumber'] ?? null;
    }

    /**
     * Extrae del P7B el certificado cuya llave pública corresponde a
     * $privateKeyPem (el titular real del certificado) y separa el resto
     * como cadena CA.
     *
     * @return array{0: \OpenSSLCertificate, 1: \OpenSSLCertificate[], 2: \OpenSSLAsymmetricKey}
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\CryptoException
     */
    private function findEndEntityCertificate(string $privateKeyPem, string $p7bDer): array
    {
        $certs = $this->extractCertsFromP7b($p7bDer);

        if (empty($certs)) {
            throw new CryptoException('No se encontraron certificados en el bundle P7B.');
        }

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

        return [$endEntityCert, $caChain, $privateKey];
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

