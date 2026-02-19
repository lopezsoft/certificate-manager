<?php

namespace Tests\Unit\Services;

use App\Services\CertificateValidatorService;
use Exception;
use Tests\TestCase;

/**
 * Tests unitarios para CertificateValidatorService.
 * No requieren base de datos ni migraciones.
 */
class CertificateValidatorServiceTest extends TestCase
{
    // ─── getExpirationDate: datos inválidos ──────────────────────────────────

    public function test_lanza_excepcion_si_el_dato_no_es_base64_valido(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        CertificateValidatorService::getExpirationDate('!!!no-es-base64!!!', 'cualquier-password');
    }

    public function test_lanza_excepcion_si_el_base64_no_es_un_pkcs12_valido(): void
    {
        // Base64 válido pero contenido inválido (no es un certificado PKCS12)
        $invalidCert = base64_encode('esto-no-es-un-certificado');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        CertificateValidatorService::getExpirationDate($invalidCert, 'password');
    }

    public function test_lanza_excepcion_si_el_password_es_incorrecto(): void
    {
        // Genera un certificado PKCS12 válido con OpenSSL en memoria
        $pkcs12 = $this->generateTestPkcs12('password-correcto');

        if ($pkcs12 === null) {
            $this->markTestSkipped('OpenSSL no está disponible para generar el certificado de prueba.');
        }

        $data = base64_encode($pkcs12);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        CertificateValidatorService::getExpirationDate($data, 'password-incorrecto');
    }

    public function test_retorna_fecha_de_expiracion_con_certificado_valido(): void
    {
        $password = 'test-password-123';
        $pkcs12   = $this->generateTestPkcs12($password);

        if ($pkcs12 === null) {
            $this->markTestSkipped('OpenSSL no está disponible para generar el certificado de prueba.');
        }

        $data   = base64_encode($pkcs12);
        $result = CertificateValidatorService::getExpirationDate($data, $password);

        // Debe retornar una fecha en formato Y-m-d H:i:s
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Genera un certificado PKCS12 auto-firmado en memoria para pruebas.
     * No toca disco ni base de datos.
     */
    private function generateTestPkcs12(string $password): ?string
    {
        if (!function_exists('openssl_pkey_new')) {
            return null;
        }

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (!$privateKey) {
            return null;
        }

        $csr = openssl_csr_new(
            ['commonName' => 'Test Certificate'],
            $privateKey
        );

        if (!$csr) {
            return null;
        }

        $cert = openssl_csr_sign($csr, null, $privateKey, 365);

        if (!$cert) {
            return null;
        }

        $pkcs12 = null;
        openssl_pkcs12_export($cert, $pkcs12, $privateKey, $password);

        return $pkcs12 ?: null;
    }
}
