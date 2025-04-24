<?php
namespace App\Common;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
class MessageExceptionResponseCopy
{
    public static function response(Exception $e): JsonResponse
    {
        if (!$e->getCode() || $e->getCode() > 508){
           return HttpResponseMessages::getResponse500([
                'message' => $e->getMessage()
           ]);
        }
        $statusCode =  $e->getCode();

        if ($e instanceof QueryException) {
            $sqlState = $e->getCode();

            // Manejo de códigos de error SQL específicos
            $statusCode = match ($sqlState) {
                '42S22', '23000' => Response::HTTP_BAD_REQUEST,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };
        }
        // Verificamos si el código es válido; si no, lo configuramos en 500.

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], $statusCode);
    }
}
