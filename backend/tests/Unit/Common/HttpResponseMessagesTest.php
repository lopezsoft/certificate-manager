<?php

namespace Tests\Unit\Common;

use App\Common\HttpResponseMessages;
use Tests\TestCase;

/**
 * Tests unitarios para HttpResponseMessages.
 * Verifica que cada método retorna el código HTTP y estructura correctos.
 * Sin base de datos ni dependencias externas.
 */
class HttpResponseMessagesTest extends TestCase
{
    // ─── Códigos de estado ────────────────────────────────────────────────────

    public function test_get_response_retorna_200(): void
    {
        $response = HttpResponseMessages::getResponse(['message' => 'OK']);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_get_response_201_retorna_201(): void
    {
        $response = HttpResponseMessages::getResponse201(['message' => 'Creado']);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_get_response_400_retorna_400(): void
    {
        $response = HttpResponseMessages::getResponse400(['message' => 'Bad request']);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_get_response_401_retorna_401(): void
    {
        $response = HttpResponseMessages::getResponse401();

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_get_response_402_retorna_402(): void
    {
        $response = HttpResponseMessages::getResponse402();

        $this->assertEquals(402, $response->getStatusCode());
    }

    public function test_get_response_403_retorna_403(): void
    {
        $response = HttpResponseMessages::getResponse403();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_get_response_404_retorna_404(): void
    {
        $response = HttpResponseMessages::getResponse404(['message' => 'No encontrado']);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_get_response_422_retorna_422(): void
    {
        $response = HttpResponseMessages::getResponse422(['message' => 'Validación fallida']);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_get_response_500_retorna_500(): void
    {
        $response = HttpResponseMessages::getResponse500(['message' => 'Error interno']);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ─── Campo success ────────────────────────────────────────────────────────

    public function test_respuestas_exitosas_incluyen_success_true(): void
    {
        $data = HttpResponseMessages::getResponse(['message' => 'OK'])->getData();
        $this->assertTrue($data->success);

        $data = HttpResponseMessages::getResponse201(['message' => 'Creado'])->getData();
        $this->assertTrue($data->success);
    }

    public function test_respuestas_de_error_incluyen_success_false(): void
    {
        foreach ([
            HttpResponseMessages::getResponse400(),
            HttpResponseMessages::getResponse401(),
            HttpResponseMessages::getResponse402(),
            HttpResponseMessages::getResponse403(),
            HttpResponseMessages::getResponse404(),
            HttpResponseMessages::getResponse422(),
            HttpResponseMessages::getResponse500(),
        ] as $response) {
            $this->assertFalse(
                $response->getData()->success,
                "Se esperaba success=false para código {$response->getStatusCode()}"
            );
        }
    }

    // ─── Datos adicionales ────────────────────────────────────────────────────

    public function test_datos_pasados_se_incluyen_en_la_respuesta(): void
    {
        $response = HttpResponseMessages::getResponse([
            'message'     => 'Operación exitosa',
            'dataRecords' => ['id' => 1],
        ]);

        $data = $response->getData();

        $this->assertEquals('Operación exitosa', $data->message);
        $this->assertObjectHasProperty('dataRecords', $data);
    }
}
