<?php

namespace App\Andes\DTOs;

class CertificateQueryResponse
{
    public function __construct(
        public bool    $found,
        public ?string $solicitudId,
        public ?string $estado,
        public ?string $serial,
        public ?string $message,
        public array   $rawResponse = [],
    ) {}

    public static function fromSoapResponse(array $response): self
    {
        return new self(
            found:       isset($response['estado']),
            solicitudId: $response['NumSolicitud'] ?? null,
            estado:      $response['estado'] ?? null,
            serial:      $response['serial'] ?? $response['Serial'] ?? null,
            message:     $response['mensaje'] ?? $response['Mensaje'] ?? null,
            rawResponse: $response,
        );
    }

    public function isEmitted(): bool
    {
        return $this->found && !empty($this->serial);
    }
}


