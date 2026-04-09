<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        CertificateException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // Excepciones de negocio propias (CertificateException e hijas)
        $this->renderable(function (CertificateException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        });

        // Modelo no encontrado (Laravel convierte ModelNotFoundException a NotFoundHttpException)
        $this->renderable(function (NotFoundHttpException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Recurso no encontrado.',
            ], 404);
        });

        // Autenticación
        $this->renderable(function (AuthenticationException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        });

        // Validación
        $this->renderable(function (ValidationException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son válidos.',
                'errors'  => $e->errors(),
            ], 422);
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}

