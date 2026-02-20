<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Excepción base para errores de negocio relacionados con solicitudes de certificado.
 */
class CertificateException extends RuntimeException
{
    public function __construct(
        string $message = 'Error en la solicitud de certificado.',
        int $code = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
