<?php

namespace App\Common;

use Illuminate\Http\JsonResponse;

class HttpResponseMessages
{
    // '200 OK'
    public static function getResponse($data = []): JsonResponse
    {
        $data['success']    = true;
        return response()->json($data);
    }
    // '201 Created'
    public static function getResponse201($data = []): JsonResponse
    {
        $data['success'] = true;
        return response()->json($data, 201);
    }

   /**
     * '400 Bad Request'
     * @param array $data
     * @return JsonResponse
     */
    public static function getResponse400(array $data = []): JsonResponse
    {
        $data['success'] = false;
        return response()->json($data, 400);
    }
    /**
     * '401 Unauthorized'
     * @return JsonResponse
     */
    public static function getResponse401(array $data = []): JsonResponse
    {
        $data['success'] = false;
        return response()->json($data, 401);
    }
    /**
     * '402 Payment Required'
     * @return JsonResponse
     */
    public static function getResponse402(): JsonResponse
    {
        return response()->json([
            'success'   => false,
        ], 402);
    }

    /**
     * '403 Forbidden'
     * @return JsonResponse
     */
    public static function getResponse403(array $data = []): JsonResponse
    {
        $data['success'] = false;
        return response()->json($data, 403);
    }
    /**
     * '404 Not Found'
     * @param array $data
     * @return JsonResponse
     */
    public static function getResponse404(array $data = []) : JsonResponse
    {
        $data['success'] = false;
        return response()->json($data, 404);
    }

    /**
     * '422 Unprocessable Entity'
     * @param array $data
     * @return JsonResponse
     */
    public static function getResponse422(array $data = []): JsonResponse
    {
        $data['success']    = false;
        return response()->json($data, 422);
    }
    /**
     * '500 Internal Server Error'
     * @param array $data
     * @return JsonResponse
     */
    public static function getResponse500(array $data = []): JsonResponse
    {
        $data['success'] = false;
        return response()->json($data, 500);
    }
}
