<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Crypto;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;
use App\Modules\Viafirma\Infrastructure\Crypto\FePnCsrBuilder;
use App\Modules\Viafirma\Infrastructure\Crypto\OpenSslCryptoService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Viafirma\UsesBundledOpenSslConfig;

final class FePnCsrBuilderTest extends TestCase
{
    use UsesBundledOpenSslConfig;

    private static ?string $privateKeyPem = null;

    private static function privateKey(): string
    {
        if (self::$privateKeyPem === null) {
            self::$privateKeyPem = (new OpenSslCryptoService('sha256', self::bundledOpensslConf()))
                ->generateKeyPair(2048)->privateKeyPem;
        }
        return self::$privateKeyPem;
    }

    private static function builder(): FePnCsrBuilder
    {
        return new FePnCsrBuilder('sha256', self::bundledOpensslConf());
    }

    private function makeInput(): CsrInputDto
    {
        return new CsrInputDto(
            profile: CertificateProfile::FE_PN,
            country: 'CO',
            state: 'ANTIOQUIA',
            locality: 'MEDELLIN',
            street: 'Carrera 65 #3',
            serialNumber: '1002000400',
            email: 'persona@correo.com',
            givenName: 'Paula',
            surname: 'Ibarra',
        );
    }

    public function test_builds_a_valid_csr_without_o_and_ou(): void
    {
        $result = self::builder()->build($this->makeInput(), self::privateKey());

        $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $result->pem);
        $subject = openssl_csr_get_subject($result->pem);
        $this->assertArrayNotHasKey('O', $subject);
        $this->assertArrayNotHasKey('OU', $subject);
        $this->assertSame('1002000400', $subject['serialNumber']);
        $this->assertSame('persona@correo.com', $subject['emailAddress']);
    }

    public function test_rejects_when_o_provided(): void
    {
        $input = new CsrInputDto(
            profile: CertificateProfile::FE_PN,
            country: 'CO', state: 'A', locality: 'M', street: 'X',
            serialNumber: '1', email: 'a@b.c', givenName: 'A', surname: 'B',
            organization: 'NO_DEBE_IR',
        );
        $this->expectException(CsrBuildException::class);
        self::builder()->build($input, self::privateKey());
    }
}

