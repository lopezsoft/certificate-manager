<?php

declare(strict_types=1);

namespace App\Common\Enums;

/**
 * Enum para códigos de estado HTTP usados en las respuestas de la API.
 *
 * Cada caso define el código numérico como valor y provee
 * una descripción legible del estado para uso en logs y mensajes.
 */
enum HttpStatusCode: int
{
    case OK = 200;
    case CREATED = 201;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case PAYMENT_REQUIRED = 402;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case CONFLICT = 409;
    case GONE = 410;
    case UNPROCESSABLE_ENTITY = 422;
    case INTERNAL_SERVER_ERROR = 500;
    case BAD_GATEWAY = 502;

    /**
     * Descripción legible del estado HTTP.
     */
    public function description(): string
    {
        return match ($this) {
            self::OK                    => 'OK',
            self::CREATED               => 'Created',
            self::BAD_REQUEST           => 'Bad Request',
            self::UNAUTHORIZED          => 'Unauthorized',
            self::PAYMENT_REQUIRED      => 'Payment Required',
            self::FORBIDDEN             => 'Forbidden',
            self::NOT_FOUND             => 'Not Found',
            self::CONFLICT              => 'Conflict',
            self::GONE                  => 'Gone',
            self::UNPROCESSABLE_ENTITY  => 'Unprocessable Entity',
            self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
            self::BAD_GATEWAY           => 'Bad Gateway',
        };
    }

    /**
     * Indica si el código representa una respuesta exitosa (2xx).
     */
    public function isSuccess(): bool
    {
        return $this->value >= 200 && $this->value < 300;
    }
}
