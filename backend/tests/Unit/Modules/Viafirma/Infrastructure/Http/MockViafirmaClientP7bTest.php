<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Application\DTOs\SubmitCsrInputDto;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Infrastructure\Crypto\OpenSslCryptoService;
use App\Modules\Viafirma\Infrastructure\Http\MockViafirmaClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Unit\Modules\Viafirma\UsesBundledOpenSslConfig;

/**
 * El mock de Sandbox debe cumplir el MISMO contrato que el cliente real: un
 * P7B parseable, con el certificado del titular emitido sobre la CSR original.
 *
 * Antes devolvía `base64_encode('MOCK_P7B_DATA_...')`, con lo que el ensamblado
 * fallaba siempre y ningún integrador podía cerrar una emisión en Sandbox
 * (reporte de Posyma). Arreglarlo no crea una diferencia entre entornos: la
 * elimina, porque el Viafirma real nunca devolvería un bundle inparseable.
 *
 * Sin BD: sólo caché de array y OpenSSL en memoria.
 */
final class MockViafirmaClientP7bTest extends TestCase
{
    use UsesBundledOpenSslConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // El mock lee la ruta del openssl.cnf de config, igual que producción.
        config(['viafirma.crypto.openssl_conf' => self::bundledOpensslConf()]);
        Cache::flush();
    }

    public function test_el_p7b_simulado_ensambla_un_p12_valido(): void
    {
        [$privateKeyPem, $csrPem] = $this->generateKeyAndCsr('1020304050');

        $client    = new MockViafirmaClient();
        $submitted = $client->submitCsr($this->inputFor($csrPem));

        $p7b = $client->downloadP7b($submitted->publicId);

        $crypto = new OpenSslCryptoService('sha256', self::bundledOpensslConf());

        // 1. El bundle se parsea y contiene el certificado de NUESTRA llave.
        $p12 = $crypto->assembleP12($privateKeyPem, $p7b, 'test-cert', 'un-pin-de-prueba-1234');

        // 2. El P12 resultante abre con el PIN y trae la cadena CA.
        $parsed = [];
        $this->assertTrue(
            openssl_pkcs12_read($p12, $parsed, 'un-pin-de-prueba-1234'),
            'El P12 ensamblado debe abrirse con el PIN de exportación.',
        );
        $this->assertArrayHasKey('cert', $parsed);
        $this->assertArrayHasKey('pkey', $parsed);
    }

    /**
     * El certificado simulado debe conservar el subject de la CSR; de lo
     * contrario AssembleP12Job abortaría con IdentityMismatchException, la
     * protección añadida tras el incidente de identidad cruzada.
     */
    public function test_conserva_la_identidad_de_la_csr(): void
    {
        [$privateKeyPem, $csrPem] = $this->generateKeyAndCsr('9876543210');

        $client    = new MockViafirmaClient();
        $submitted = $client->submitCsr($this->inputFor($csrPem));
        $p7b       = $client->downloadP7b($submitted->publicId);

        $crypto = new OpenSslCryptoService('sha256', self::bundledOpensslConf());

        $this->assertSame(
            $crypto->extractCsrSubjectIdentity($csrPem),
            $crypto->extractSubjectIdentity($privateKeyPem, $p7b),
            'El certificado simulado debe emitirse con el serialNumber de la CSR.',
        );
    }

    /**
     * Sin la CSR en caché el mock no puede simular una emisión coherente.
     * Debe fallar con un mensaje accionable en vez de devolver basura que
     * reviente más adelante en el ensamblado.
     */
    public function test_falla_con_mensaje_claro_si_no_hay_csr_en_cache(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no hay CSR en cach/i');

        (new MockViafirmaClient())->downloadP7b('MOCK-PUB-INEXISTENTE');
    }

    /** El enlace KYC debe apuntar a una ruta que exista (antes daba 404). */
    public function test_el_enlace_kyc_apunta_al_callback_real(): void
    {
        $link = (new MockViafirmaClient())->getAccreditationLink('MOCK-REQ-1', 'MOCK-PUB-1');

        $this->assertSame(
            route('viafirma.kyc-callback', ['publicId' => 'MOCK-PUB-1']),
            $link,
        );
    }

    /** @return array{0: string, 1: string} [privateKeyPem, csrPem] */
    private function generateKeyAndCsr(string $serialNumber): array
    {
        $opts = ['digest_alg' => 'sha256', 'config' => self::bundledOpensslConf()];

        $key = openssl_pkey_new($opts + [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $privateKeyPem, null, $opts);

        $csr = openssl_csr_new([
            'CN'           => 'TITULAR DE PRUEBA',
            'O'            => 'EMPRESA DE PRUEBA SAS',
            'C'            => 'CO',
            'serialNumber' => $serialNumber,
        ], $key, $opts);
        openssl_csr_export($csr, $csrPem);

        return [$privateKeyPem, $csrPem];
    }

    private function inputFor(string $csrPem): SubmitCsrInputDto
    {
        return new SubmitCsrInputDto(
            identityType:     IdentityType::IDC,
            countryCode:      'CO',
            identity:         '1020304050',
            raCode:           'RA-TEST',
            codProfile:       'FE-PJ',
            emailCertificate: 'test@example.com',
            csrBase64:        base64_encode($csrPem),
        );
    }
}
