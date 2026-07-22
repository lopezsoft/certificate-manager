<?php

declare(strict_types=1);

namespace App\Services\Certificate;

use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use RuntimeException;
use Illuminate\Support\Str;

class SelfSignedCertificateGenerator
{
    /**
     * Genera un P12 binario auto-firmado de prueba.
     *
     * @param CryptoServiceContract $crypto Servicio de criptografía.
     * @param array $subject Información del sujeto (DN) p.ej. ['CN' => 'SANDBOX ...']
     * @param int $validityDays Días de validez.
     * @param string $exportPassword Contraseña del P12 generado.
     * @param string $friendlyName Nombre amigable del certificado dentro del P12.
     * @return string Contenido binario del archivo P12.
     *
     * @throws RuntimeException Si falla la generación por parte de OpenSSL.
     */
    public function generateP12(
        CryptoServiceContract $crypto,
        array $subject,
        int $validityDays,
        string $exportPassword,
        string $friendlyName
    ): string {
        // 1. Generar par RSA
        $keyPair = $crypto->generateKeyPair(2048);
        $privateKey = openssl_pkey_get_private($keyPair->privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('Error obteniendo llave privada: ' . openssl_error_string());
        }

        // 2. Configuración para OpenSSL (soporte entorno WAMP/Windows)
        $config = ['digest_alg' => 'sha256'];
        $opensslConf = config('viafirma.crypto.openssl_conf');
        if ($opensslConf && is_file($opensslConf)) {
            $config['config'] = $opensslConf;
        }

        // 3. Crear CSR
        $csr = openssl_csr_new($subject, $privateKey, $config);
        if ($csr === false) {
            throw new RuntimeException('Error creando CSR: ' . openssl_error_string());
        }

        // 4. Auto-firmar el certificado
        $cert = openssl_csr_sign($csr, null, $privateKey, $validityDays, $config);
        if ($cert === false) {
            throw new RuntimeException('Error firmando certificado: ' . openssl_error_string());
        }

        // 5. Exportar certificado a PEM
        openssl_x509_export($cert, $certPem);
        if (!$certPem) {
            throw new RuntimeException('Error exportando certificado X509: ' . openssl_error_string());
        }

        // 6. Ensamblar en P12. Pasamos certPem directo porque extractCertsFromP7b
        // tiene un fallback que extrae los bloques PEM sueltos.
        return $crypto->assembleP12(
            privateKeyPem:  $keyPair->privateKeyPem,
            p7bDer:         $certPem,
            friendlyName:   $friendlyName,
            exportPassword: $exportPassword
        );
    }
}
