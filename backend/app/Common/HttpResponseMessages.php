<?php

declare(strict_types=1);

namespace App\Common;

use App\Common\Enums\HttpStatusCode;
use Illuminate\Http\JsonResponse;

/**
 * Factoría de respuestas HTTP estandarizadas para la API.
 *
 * Utiliza el enum HttpStatusCode para garantizar tipado fuerte
 * y consistencia en los códigos de respuesta.
 */
class HttpResponseMessages
{
    /**
     * Respuesta exitosa genérica.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::OK);
    }

    /**
     * Respuesta 201 Created.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse201(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::CREATED);
    }

    /**
     * Respuesta 400 Bad Request.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse400(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::BAD_REQUEST);
    }

    /**
     * Respuesta 401 Unauthorized.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse401(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::UNAUTHORIZED);
    }

    /**
     * Respuesta 402 Payment Required.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse402(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::PAYMENT_REQUIRED);
    }

    /**
     * Respuesta 403 Forbidden.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse403(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::FORBIDDEN);
    }

    /**
     * Respuesta 404 Not Found.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse404(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::NOT_FOUND);
    }
    /**
     * Respuesta 409 Conflict.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse409(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::CONFLICT);
    }

    /**
     * Respuesta 410 Gone.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse410(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::GONE);
    }

    /**
     * Respuesta 422 Unprocessable Entity.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse422(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::UNPROCESSABLE_ENTITY);
    }

    /**
     * Respuesta 500 Internal Server Error.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse500(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::INTERNAL_SERVER_ERROR);
    }


    /**
     * Respuesta 502 Bad Gateway.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponse502(array $data = []): JsonResponse
    {
        return self::buildResponse($data, HttpStatusCode::BAD_GATEWAY);
    }

    /**
     * Respuesta con código HTTP dinámico (resuelto desde el enum).
     *
     * Útil cuando el código llega como int desde una excepción de negocio.
     * Si el código no existe en el enum, se responde con 500.
     *
     * @param array<string, mixed> $data
     */
    public static function getResponseForStatus(int $statusCode, array $data = []): JsonResponse
    {
        $status = HttpStatusCode::tryFrom($statusCode) ?? HttpStatusCode::INTERNAL_SERVER_ERROR;

        return self::buildResponse($data, $status);
    }

    /**
     * Construye la respuesta JSON estandarizada.
     *
     * @param array<string, mixed> $data
     */
    private static function buildResponse(array $data, HttpStatusCode $status): JsonResponse
    {
        $data['success'] = $status->isSuccess();

        return response()->json($data, $status->value);
    }
}
