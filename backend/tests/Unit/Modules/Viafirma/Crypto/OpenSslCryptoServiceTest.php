<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Crypto;

use App\Modules\Viafirma\Infrastructure\Crypto\OpenSslCryptoService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Viafirma\UsesBundledOpenSslConfig;

final class OpenSslCryptoServiceTest extends TestCase
{
    use UsesBundledOpenSslConfig;

    private static function svc(): OpenSslCryptoService
    {
        return new OpenSslCryptoService('sha256', self::bundledOpensslConf());
    }

    public function test_generate_key_pair_produces_valid_rsa_2048(): void
    {
        $pair = self::svc()->generateKeyPair(2048);

        $this->assertSame(2048, $pair->bits);
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $pair->privateKeyPem);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $pair->publicKeyPem);

        $res = openssl_pkey_get_private($pair->privateKeyPem);
        $this->assertNotFalse($res);
        $details = openssl_pkey_get_details($res);
        $this->assertSame(OPENSSL_KEYTYPE_RSA, $details['type']);
        $this->assertSame(2048, $details['bits']);
    }

    public function test_generate_key_pair_rejects_weak_size(): void
    {
        $this->expectException(\App\Modules\Viafirma\Domain\Exceptions\CryptoException::class);
        self::svc()->generateKeyPair(1024);
    }

    public function test_sha256_hex_is_deterministic(): void
    {
        $this->assertSame(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            self::svc()->sha256Hex('')
        );
    }
}

