<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\CertificateException;
use App\Exceptions\EmailNotConfiguredException;
use App\Exceptions\InvalidFileException;
use Tests\TestCase;

/**
 * Tests unitarios para las excepciones custom y el Handler.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class HandlerTest extends TestCase
{
    public function test_certificate_exception_retorna_400(): void
    {
        $response = $this->handleException(
            new CertificateException('Error de certificado', 400)
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Error de certificado',
            ]);
    }

    public function test_invalid_file_exception_retorna_422(): void
    {
        $response = $this->handleException(
            new InvalidFileException('Archivo no válido')
        );

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Archivo no válido',
            ]);
    }

    public function test_email_not_configured_exception_retorna_400(): void
    {
        $response = $this->handleException(
            new EmailNotConfiguredException()
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_model_not_found_retorna_404_con_mensaje_sanitizado(): void
    {
        $response = $this->handleException(
            new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Recurso no encontrado.')
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Recurso no encontrado.',
            ]);
    }

    public function test_validation_exception_retorna_422(): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['name' => ''],
            ['name' => 'required']
        );

        $response = $this->handleException(
            new \Illuminate\Validation\ValidationException($validator)
        );

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    /**
     * Helper para lanzar la excepción a través del Handler de la app.
     */
    private function handleException(\Throwable $e): \Illuminate\Testing\TestResponse
    {
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $request = \Illuminate\Http\Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $handler->render($request, $e);

        return \Illuminate\Testing\TestResponse::fromBaseResponse($response);
    }
}


