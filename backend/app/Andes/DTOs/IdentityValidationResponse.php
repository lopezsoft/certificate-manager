<?php

namespace App\Andes\DTOs;

use App\Andes\Enums\AndesTokenStatusEnum;
use App\Andes\Enums\AndesValidationTypeEnum;

class IdentityValidationResponse
{
    public function __construct(
        public bool   $success,
        public ?string $token,
        public ?AndesValidationTypeEnum $validationType,
        public AndesTokenStatusEnum     $tokenStatus,
        public ?array  $questions,       // Preguntas del cuestionario (ShowExam)
        public ?string $message,
        public array   $rawResponse = [],
    ) {}

    public static function fromApiResponse(array $response): self
    {
        $status = AndesTokenStatusEnum::from((int)($response['estado'] ?? 0));

        $typeRaw = $response['tipo_validacion'] ?? $response['TipoValidacion'] ?? null;
        $type    = $typeRaw ? AndesValidationTypeEnum::tryFrom($typeRaw) : null;

        return new self(
            success:        $status->isSuccessful() || $status === AndesTokenStatusEnum::EN_CURSO,
            token:          $response['Token'] ?? $response['token'] ?? null,
            validationType: $type,
            tokenStatus:    $status,
            questions:      $response['preguntas'] ?? $response['Preguntas'] ?? null,
            message:        $response['mensaje'] ?? $response['Mensaje'] ?? null,
            rawResponse:    $response,
        );
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(
            success:        false,
            token:          null,
            validationType: null,
            tokenStatus:    AndesTokenStatusEnum::FALLIDO,
            questions:      null,
            message:        $message,
            rawResponse:    $raw,
        );
    }
}


