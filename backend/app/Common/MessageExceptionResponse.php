<?php
namespace App\Common;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessageExceptionResponse
{
    public static function response(Exception $e): JsonResponse
    {
        // Obtener el código de estado HTTP a partir de la excepción
        $statusCode = self::getStatusCodeFromException($e);

        // Construir el mensaje de respuesta
        $response = [
            'success'   => false,
            'message'   => self::getMessageFromException($e)
        ];
        if ($statusCode >= 500) {
           // line and file
            $response['line'] = $e->getLine();
            $response['file'] = $e->getFile();
        }
        // Si es una excepción de validación, incluir los errores
        if ($e instanceof ValidationException) {
            $response['errors'] = $e->errors();
        }

        // Registrar la excepción para propósitos de depuración
        self::logException($e, $statusCode);

        // Retornar la respuesta JSON con el código de estado HTTP
        return response()->json($response, $statusCode);
    }

    private static function getStatusCodeFromException(Exception $e): int
    {
        // Mapear tipos de excepción a códigos de estado HTTP
        return match (true) {
            $e instanceof ValidationException => Response::HTTP_UNPROCESSABLE_ENTITY,          // 422
            $e instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,              // 401
            $e instanceof AuthorizationException => Response::HTTP_FORBIDDEN,                  // 403
            $e instanceof NotFoundHttpException => Response::HTTP_NOT_FOUND,                   // 404
            $e instanceof MethodNotAllowedHttpException => Response::HTTP_METHOD_NOT_ALLOWED,  // 405
            $e instanceof HttpException => $e->getStatusCode(),                                // Usa el código de estado de la excepción
            $e instanceof QueryException => self::getStatusCodeFromQueryException($e),
            ($e->getCode() >= 400 && $e->getCode() < 600)    => $e->getCode(),                 // Códigos de estado personalizados
            default => Response::HTTP_INTERNAL_SERVER_ERROR,                                   // 500
        };
    }

    private static function getMessageFromException(Exception $e): string
    {
        // Personalizar el mensaje según el tipo de excepción
        return match (true) {
            $e instanceof ValidationException => 'Error de validación de datos.',
            $e instanceof AuthenticationException => 'No autenticado.',
            $e instanceof AuthorizationException => "Acceso no autorizado. {$e->getMessage()}", // Incluir mensaje personalizado
            $e instanceof NotFoundHttpException => 'Recurso no encontrado.',
            $e instanceof MethodNotAllowedHttpException => 'Método no permitido.',
            $e instanceof HttpException, $e instanceof QueryException, ($e->getCode() >= 400 && $e->getCode() < 600) => $e->getMessage(),
            default => 'Error interno del servidor.'.($e->getMessage() ? ' '.$e->getMessage() : ''),
        };
    }

    private static function getStatusCodeFromQueryException(QueryException $e): int
    {
        $sqlState = $e->getCode();

        // Manejo de códigos de error SQL específicos
        return match ($sqlState) {
            '42S22', // Columna no encontrada
            '23000' => Response::HTTP_BAD_REQUEST, // Violación de restricción de integridad
            default => Response::HTTP_INTERNAL_SERVER_ERROR, // 500
        };
    }

    private static function logException(Exception $e, int $statusCode): void
    {
        // Registrar excepciones de servidor (500 en adelante)
        if ($statusCode >= 500) {
            Log::error('Excepción capturada en MessageExceptionResponse', [
                'exception' => $e,
            ]);
        } else {
            Log::warning('Excepción manejada en MessageExceptionResponse', [
                'exception' => $e,
            ]);
        }
    }
}
