<?php

namespace App\Exceptions;

use Throwable;

/**
 * Se lanza cuando un archivo subido no cumple con los requisitos
 * (tipo, tamaño, cantidad máxima, etc.).
 */
class InvalidFileException extends CertificateException
{
    public function __construct(
        string $message = 'El archivo proporcionado no es válido.',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
