<?php

namespace App\Andes\Exceptions;

use RuntimeException;

class AndesAuthenticationException extends RuntimeException
{
    public function __construct(string $reason = 'No se pudo autenticar con ANDES ID API')
    {
        parent::__construct($reason, 401);
    }
}

