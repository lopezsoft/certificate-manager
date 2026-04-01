<?php

namespace App\Exceptions;

use Throwable;

/**
 * Se lanza cuando la empresa no tiene configurado un correo electrónico
 * de destino para el envío de solicitudes de certificado.
 */
class EmailNotConfiguredException extends CertificateException
{
    public function __construct(
        string $message = 'El correo electrónico de destino no está configurado.',
        int $code = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
