<?php

namespace App\Payments\DTOs;

class AcceptanceTokenResponse
{
    public function __construct(
        public readonly string $token,
        public readonly string $permalink,
        public readonly string $type,
    ) {}

    public static function fromWompiResponse(array $data): self
    {
        $merchant = $data['data'] ?? $data;
        $policy   = $merchant['presigned_acceptance'] ?? [];

        return new self(
            token:     $policy['acceptance_token'] ?? '',
            permalink: $policy['permalink'] ?? '',
            type:      $policy['type'] ?? 'END_USER_POLICY',
        );
    }
}

