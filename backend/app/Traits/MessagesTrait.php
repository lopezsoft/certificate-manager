<?php

namespace App\Traits;

use App\Common\DateFunctions;
use App\Common\VerificationDigit;
use Illuminate\Http\JsonResponse;

trait MessagesTrait
{

    public static function getRealTimeXls($date = null): string
    {
        return DateFunctions::getRealTimeXls($date);
    }

    public static function getRealDateXls($date = null): string
    {
        return DateFunctions::getRealDateXls($date);
    }

    public static function getRealDate($date = null, $validateYear = false): string
    {
        return DateFunctions::getRealDate($date, $validateYear);
    }

    /**
     * 200 Created
     */
    public static function getResponse($data = []): JsonResponse
    {
        $data['success']    = true;
        return response()->json($data);
    }

    /**
     * 201 Created
     */
    public static function getResponse201($data = []): JsonResponse
    {
        $data['success']    = true;
        $data['message']    = 'Recurso creado exitosamente.';
        return response()->json($data, 201);
    }

    /**
     * 400 Bad Request
     */
    public static function getResponse400($data = []): JsonResponse
    {
        $data['success']    = false;
        return response()->json($data, 400);
    }

   public  static function getResponse401(): JsonResponse
    {
        return response()->json([
            'success'   => false,
            'message'   => 'Acceso No autorizado',
        ], 401);
    }

    public static function getResponse422($data = []): JsonResponse
    {
        $data['success']    = false;
        return response()->json($data, 422);
    }

    /**
     * 500 Internal Server Error
     */
    public static function getResponse500($msg  = null): JsonResponse
    {
        return response()->json([
            'success'   => false,
            'message'   => $msg ?? 'Internal Server Error',
            'payload'   => 'Internal Server Error',
        ], 500);
    }

    public static function digitVerificacion(int $Number = 0): int
    {
        return VerificationDigit::getDigit($Number);
    }


    /**
     * Retorna una respuesta con los registros indicados
     */
    public static function getRecordsResponse($lis = array(), $total = 0): JsonResponse
    {
        return response()->json([
            'success'   => true,
            'dataRecords'   => [
                'data' => $lis
            ],
            'total'     => $total,
        ]);
    }

    public static function getErrorResponse($msg  = ''): JsonResponse
    {
        return response()->json([
            'success'   => false,
            'message'   => $msg,
            'payload'   => 'Internal Server Error',
        ],500);
    }
}
