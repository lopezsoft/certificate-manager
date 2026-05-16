<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Crypto;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;
use App\Modules\Viafirma\Infrastructure\Crypto\FePjCsrBuilder;
use App\Modules\Viafirma\Infrastructure\Crypto\OpenSslCryptoService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Viafirma\UsesBundledOpenSslConfig;

final class FePjCsrBuilderTest extends TestCase
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

    private static function builder(): FePjCsrBuilder
    {
        return new FePjCsrBuilder('sha256', self::bundledOpensslConf());
    }

    private function makeInput(): CsrInputDto
    {
        return new CsrInputDto(
            profile: CertificateProfile::FE_PJ,
            country: 'CO',
            state: 'ANTIOQUIA',
            locality: 'MEDELLIN',
            street: 'Carrera 65 #3',
            serialNumber: '900400300',
            email: 'info@empresa.com',
            givenName: 'Paula',
            surname: 'Ibarra',
            organization: 'MI COMPANIA SAS',
            organizationUnit: 'FACTURACION',
            organizationType: OrganizationType::EXTRANJERAS,
        );
    }

    public function test_builds_a_valid_csr_with_10_attributes(): void
    {
        $result = self::builder()->build($this->makeInput(), self::privateKey());

        $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $result->pem);
        $this->assertNotSame('', $result->base64);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result->fingerprint);

        $details = openssl_csr_get_subject($result->pem);
        $this->assertSame('CO', $details['C']);
        $this->assertSame('ANTIOQUIA', $details['ST']);
        $this->assertSame('MEDELLIN', $details['L']);
        $this->assertSame('MI COMPANIA SAS', $details['O']);
        $this->assertSame('FACTURACION', $details['OU']);
        $this->assertSame('900400300', $details['serialNumber']);
        $this->assertSame('info@empresa.com', $details['emailAddress']);
        $this->assertSame('Paula', $details['GN']);
        $this->assertSame('Ibarra', $details['SN']);
    }

    public function test_rejects_wrong_profile(): void
    {
        $input = new CsrInputDto(
            profile: CertificateProfile::FE_PN,
            country: 'CO', state: 'A', locality: 'M', street: 'X',
            serialNumber: '1', email: 'a@b.c', givenName: 'A', surname: 'B',
        );
        $this->expectException(CsrBuildException::class);
        self::builder()->build($input, self::privateKey());
    }

    public function test_rejects_missing_organization(): void
    {
        $input = new CsrInputDto(
            profile: CertificateProfile::FE_PJ,
            country: 'CO', state: 'A', locality: 'M', street: 'X',
            serialNumber: '1', email: 'a@b.c', givenName: 'A', surname: 'B',
            organization: null, organizationUnit: 'OU1',
        );
        $this->expectException(CsrBuildException::class);
        self::builder()->build($input, self::privateKey());
    }
}

