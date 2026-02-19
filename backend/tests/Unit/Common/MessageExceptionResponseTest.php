<?php

namespace Tests\Unit\Common;

use App\Common\MessageExceptionResponse;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Mockery;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Tests unitarios para MessageExceptionResponse.
 * Verifica el mapeo correcto de excepciones → códigos HTTP.
 * Sin base de datos ni dependencias externas.
 */
class MessageExceptionResponseTest extends TestCase
{
    // ─── Códigos de estado HTTP ───────────────────────────────────────────────

    public function test_excepcion_generica_con_codigo_400_retorna_400(): void
    {
        $response = MessageExceptionResponse::response(new Exception('Error bad request', 400));

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($response->getData()->success);
    }

    public function test_excepcion_generica_con_codigo_404_retorna_404(): void
    {
        $response = MessageExceptionResponse::response(new Exception('No encontrado', 404));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_excepcion_sin_codigo_retorna_500(): void
    {
        $response = MessageExceptionResponse::response(new Exception('Error inesperado'));

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertFalse($response->getData()->success);
    }

    public function test_not_found_http_exception_retorna_404(): void
    {
        $response = MessageExceptionResponse::response(new NotFoundHttpException());

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_method_not_allowed_retorna_405(): void
    {
        $response = MessageExceptionResponse::response(new MethodNotAllowedHttpException(['GET', 'POST']));

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_authentication_exception_retorna_401(): void
    {
        $response = MessageExceptionResponse::response(new AuthenticationException());

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_authorization_exception_retorna_403(): void
    {
        $response = MessageExceptionResponse::response(new AuthorizationException('Sin permiso'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    // ─── Estructura de la respuesta ───────────────────────────────────────────

    public function test_respuesta_siempre_incluye_campo_success_false(): void
    {
        $response = MessageExceptionResponse::response(new Exception('Test', 400));
        $data     = $response->getData();

        $this->assertObjectHasProperty('success', $data);
        $this->assertFalse($data->success);
    }

    public function test_respuesta_siempre_incluye_campo_message(): void
    {
        $response = MessageExceptionResponse::response(new Exception('Mi mensaje de error', 400));
        $data     = $response->getData();

        $this->assertObjectHasProperty('message', $data);
        $this->assertEquals('Mi mensaje de error', $data->message);
    }

    public function test_error_500_incluye_linea_y_archivo_en_respuesta(): void
    {
        $response = MessageExceptionResponse::response(new Exception('Error interno'));
        $data     = $response->getData();

        $this->assertObjectHasProperty('line', $data);
        $this->assertObjectHasProperty('file', $data);
    }

    public function test_error_400_no_incluye_linea_ni_archivo(): void
    {
        $response = MessageExceptionResponse::response(new Exception('Bad request', 400));
        $data     = $response->getData();

        $this->assertObjectNotHasProperty('line', $data);
        $this->assertObjectNotHasProperty('file', $data);
    }

    // ─── ValidationException ──────────────────────────────────────────────────

    public function test_validation_exception_retorna_422_con_errores(): void
    {
        $validator = Mockery::mock(Validator::class);
        $validator->shouldReceive('errors')->andReturn(new \Illuminate\Support\MessageBag([
            'campo' => ['El campo es requerido.'],
        ]));

        $exception = new ValidationException($validator);
        $response  = MessageExceptionResponse::response($exception);
        $data      = $response->getData();

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertObjectHasProperty('errors', $data);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
