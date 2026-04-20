<?php

namespace App\Andes\DTOs;

class CertificateEmissionResponse
{
    public function __construct(
        public bool    $success,
        public ?string $solicitudId,
        public ?string $estado,
        public ?string $message,
        public array   $rawResponse = [],
    ) {}

    public static function fromSoapResponse(array $response): self
    {
        $success     = isset($response['estado']) && (int)$response['estado'] === 0;
        $solicitudId = $response['NumSolicitud'] ?? $response['numSolicitud'] ?? null;

        return new self(
            success:     $success,
            solicitudId: $solicitudId,
            estado:      $response['estado'] ?? null,
            message:     $response['mensaje'] ?? $response['Mensaje'] ?? null,
            rawResponse: $response,
        );
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(
            success:     false,
            solicitudId: null,
            estado:      null,
            message:     $message,
            rawResponse: $raw,
        );
    }
}


