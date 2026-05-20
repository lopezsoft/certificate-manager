<?php

declare(strict_types=1);

namespace App\Exceptions\Certificate;

use RuntimeException;
use Throwable;

/**
 * Excepción de negocio para errores controlados durante la emisión de un
 * certificado, independientemente del proveedor (mail, viafirma, etc.).
 *
 * El orquestador captura esta excepción y la traduce a una respuesta HTTP
 * coherente (4xx). Para errores transientes/remotos cada proveedor lanza
 * sus excepciones específicas y el orquestador las re-lanza tal cual.
 */
class CertificateIssuanceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly ?string $providerName = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }
}

