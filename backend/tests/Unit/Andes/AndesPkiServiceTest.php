<?php

namespace Tests\Unit\Andes;

use App\Andes\DTOs\CertificateEmissionRequest;
use App\Andes\Exceptions\AndesCertificateEmissionException;
use App\Andes\Services\AndesPkiService;
use App\Andes\Services\AndesSoapClientFactory;
use Tests\TestCase;

/**
 * Tests unitarios para AndesPkiService.
 * Usa un FakeSoapClient que implementa __call() para simular respuestas SOAP.
 */
class AndesPkiServiceTest extends TestCase
{
    private function buildEmissionRequest(int $tipoCert = 11): CertificateEmissionRequest
    {
        return new CertificateEmissionRequest(
            tipoCert:     $tipoCert,
            tipoDoc:      1,
            documento:    '12345678',
            nombres:      'JUAN CARLOS',
            apellidos:    'PEREZ GARCIA',
            municipio:    5001,
            direccion:    'Calle 10 # 20-30',
            email:        'juan@test.com',
            emailEnt:     'empresa@test.com',
            celular:      '3001234567',
            fechaCert:    '2027-04-20',
            formato:      3,
            vigenciaCert: 3,
            soporte:      base64_encode('fake_zip_content'),
        );
    }

    /** Crea un factory que devuelve un FakeSoapClient con respuesta configurable */
    private function fakeFactory(string $method, mixed $returns): AndesSoapClientFactory
    {
        $fakeClient = new class($method, $returns) {
            public function __construct(
                private readonly string $method,
                private readonly mixed  $returns,
            ) {}

            public function __call(string $name, array $args): mixed
            {
                if ($name === $this->method) {
                    if ($this->returns instanceof \Throwable) {
                        throw $this->returns;
                    }
                    return $this->returns;
                }
                throw new \RuntimeException("Método SOAP no esperado: {$name}");
            }

            public function __setSoapHeaders(array $h): void {}
        };

        $factory = $this->createMock(AndesSoapClientFactory::class);
        $factory->method('create')->willReturn($fakeClient);

        return $factory;
    }

    public function test_solicitud_exitosa_devuelve_response_con_solicitud_id(): void
    {
        $factory = $this->fakeFactory('CertificadoFacturacionElectronica', [
            'estado'       => 0,
            'NumSolicitud' => 'SOL-2026-001',
            'mensaje'      => 'Solicitud recibida correctamente',
        ]);

        $service  = new AndesPkiService($factory);
        $response = $service->requestElectronicInvoiceCertificate($this->buildEmissionRequest());

        $this->assertTrue($response->success);
        $this->assertSame('SOL-2026-001', $response->solicitudId);
        $this->assertSame('Solicitud recibida correctamente', $response->message);
    }

    public function test_solicitud_persona_juridica_incluye_datos_entidad(): void
    {
        $dto = new CertificateEmissionRequest(
            tipoCert:     10,
            tipoDoc:      1,
            documento:    '12345678',
            nombres:      'LEWIS OSWALDO',
            apellidos:    'LOPEZ GOMEZ',
            municipio:    5001,
            direccion:    'Calle 1 # 2-3',
            email:        'lewis@empresa.com',
            emailEnt:     'facturacion@empresa.com',
            celular:      '3009876543',
            fechaCert:    '2027-04-20',
            formato:      3,
            vigenciaCert: 3,
            soporte:      base64_encode('zip_content'),
            tipoDocEnt:   2,
            documentoEnt: '900123456',
            razonSocial:  'EMPRESA TEST SAS',
            municipioEnt: 5001,
            direccionEnt: 'Cra 10 # 5-20',
            cargo:        'Representante Legal',
        );

        $params = $dto->toSoapArray();

        $this->assertArrayHasKey('TipoDocEnt', $params);
        $this->assertArrayHasKey('documentoEnt', $params);
        $this->assertArrayHasKey('razonsocial', $params);
        $this->assertSame(10, $params['tipoCert']);
        $this->assertSame('EMPRESA TEST SAS', $params['razonsocial']);
    }

    public function test_solicitud_persona_natural_no_incluye_datos_entidad(): void
    {
        $dto    = $this->buildEmissionRequest(11);
        $params = $dto->toSoapArray();

        $this->assertArrayNotHasKey('TipoDocEnt', $params);
        $this->assertArrayNotHasKey('documentoEnt', $params);
        $this->assertArrayNotHasKey('razonsocial', $params);
    }

    public function test_soap_fault_lanza_excepcion_emision(): void
    {
        $factory = $this->fakeFactory(
            'CertificadoFacturacionElectronica',
            new \SoapFault('Server', 'Error en servidor ANDES')
        );

        $service = new AndesPkiService($factory);

        $this->expectException(AndesCertificateEmissionException::class);
        $service->requestElectronicInvoiceCertificate($this->buildEmissionRequest());
    }

    public function test_query_request_status_devuelve_response(): void
    {
        $factory = $this->fakeFactory('ConsultarSolicitud', [
            'estado'       => 'EMITIDO',
            'NumSolicitud' => 'SOL-2026-001',
            'serial'       => 'CERT-SERIAL-XYZ',
            'mensaje'      => 'Certificado emitido',
        ]);

        $service  = new AndesPkiService($factory);
        $response = $service->queryRequestStatus('SOL-2026-001', '12345678');

        $this->assertTrue($response->found);
        $this->assertTrue($response->isEmitted());
        $this->assertSame('CERT-SERIAL-XYZ', $response->serial);
    }

    public function test_revoke_certificate_retorna_true_en_exito(): void
    {
        $factory = $this->fakeFactory('Revocacion', ['estado' => 0, 'mensaje' => 'Revocado']);

        $service = new AndesPkiService($factory);
        $result  = $service->revokeCertificate('SERIAL-123', '12345678', 1, 'A solicitud del titular');

        $this->assertTrue($result);
    }
}