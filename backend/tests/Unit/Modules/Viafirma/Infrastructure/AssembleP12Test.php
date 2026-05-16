<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure;

use App\Modules\Viafirma\Domain\Exceptions\CryptoException;
use App\Modules\Viafirma\Infrastructure\Crypto\OpenSslCryptoService;
use Tests\TestCase;

/**
 * Tests del ensamblaje P12 real (V-403 / Sprint 5 hardening).
 *
 * Genera un par RSA auto-firmado + certificado para probar el flujo completo.
 */
class AssembleP12Test extends TestCase
{
    private OpenSslCryptoService $crypto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crypto = new OpenSslCryptoService(
            digestAlg: 'sha256',
            opensslConf: config_path('viafirma/openssl.cnf'),
        );
    }

    /** @test */
    public function it_rejects_empty_private_key(): void
    {
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('Material criptográfico vacío');

        $this->crypto->assembleP12('', 'some-p7b-data', 'test', 'pin123');
    }

    /** @test */
    public function it_rejects_empty_p7b(): void
    {
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('Material criptográfico vacío');

        $this->crypto->assembleP12('fake-pem', '', 'test', 'pin123');
    }

    /** @test */
    public function it_rejects_empty_password(): void
    {
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('PIN de exportación');

        $this->crypto->assembleP12('fake-pem', 'fake-p7b', 'test', '');
    }

    /** @test */
    public function it_rejects_invalid_p7b_data(): void
    {
        // Generate a valid key pair
        $keyPair = $this->crypto->generateKeyPair(2048);

        $this->expectException(CryptoException::class);

        $this->crypto->assembleP12(
            $keyPair->privateKeyPem,
            'definitely-not-a-p7b',
            'test',
            'pin123'
        );
    }

    /** @test */
    public function it_assembles_p12_from_self_signed_cert(): void
    {
        // Generate key pair
        $keyPair = $this->crypto->generateKeyPair(2048);

        // Create self-signed certificate (simulating what Viafirma would return)
        $privateKey = openssl_pkey_get_private($keyPair->privateKeyPem);
        $this->assertNotFalse($privateKey, 'Failed to load private key');

        $csrConfig = ['digest_alg' => 'sha256'];
        $opensslConf = config_path('viafirma/openssl.cnf');
        if (is_file($opensslConf)) {
            $csrConfig['config'] = $opensslConf;
        }

        $csr = openssl_csr_new(
            ['CN' => 'Test Viafirma Cert'],
            $privateKey,
            $csrConfig,
        );
        $this->assertNotFalse($csr, 'Failed to create CSR');

        $cert = openssl_csr_sign($csr, null, $privateKey, 365, $csrConfig);
        $this->assertNotFalse($cert, 'Failed to sign certificate');

        $certPem = '';
        openssl_x509_export($cert, $certPem);
        $this->assertNotEmpty($certPem);

        // The cert PEM simulates a P7B with a single cert
        $p12 = $this->crypto->assembleP12(
            $keyPair->privateKeyPem,
            $certPem,
            'viafirma-test',
            'test-pin-123',
        );

        $this->assertNotEmpty($p12, 'P12 binary should not be empty');

        // Verify it's a valid PKCS#12
        $parsed = [];
        $readOk = openssl_pkcs12_read($p12, $parsed, 'test-pin-123');
        $this->assertTrue($readOk, 'P12 should be parseable with the correct PIN');
        $this->assertArrayHasKey('cert', $parsed);
        $this->assertArrayHasKey('pkey', $parsed);
    }

    /** @test */
    public function it_rejects_mismatched_key_and_cert(): void
    {
        // Generate two different key pairs
        $keyPair1 = $this->crypto->generateKeyPair(2048);
        $keyPair2 = $this->crypto->generateKeyPair(2048);

        // Create cert with keyPair2
        $privateKey2 = openssl_pkey_get_private($keyPair2->privateKeyPem);

        $csrConfig = ['digest_alg' => 'sha256'];
        $opensslConf = config_path('viafirma/openssl.cnf');
        if (is_file($opensslConf)) {
            $csrConfig['config'] = $opensslConf;
        }

        $csr = openssl_csr_new(['CN' => 'Mismatch Test'], $privateKey2, $csrConfig);
        $cert = openssl_csr_sign($csr, null, $privateKey2, 365, $csrConfig);
        $certPem = '';
        openssl_x509_export($cert, $certPem);

        // Try to assemble P12 with keyPair1 (mismatch)
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('no se encontró un certificado que corresponda');

        $this->crypto->assembleP12(
            $keyPair1->privateKeyPem,
            $certPem,
            'mismatch',
            'pin123',
        );
    }
}
