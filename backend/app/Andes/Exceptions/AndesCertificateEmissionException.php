<?php

namespace App\Andes\Exceptions;

use RuntimeException;

class AndesCertificateEmissionException extends RuntimeException
{
    public function __construct(string $reason, public readonly array $rawResponse = [])
    {
        parent::__construct($reason, 502);
    }
}

