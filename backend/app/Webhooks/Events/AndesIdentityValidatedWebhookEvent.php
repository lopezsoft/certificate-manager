<?php

namespace App\Webhooks\Events;

use App\Andes\Models\AndesIdentityValidation;
use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class AndesIdentityValidatedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly AndesIdentityValidation $validation,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::ANDES_IDENTITY_VALIDATED;
    }

    public function companyId(): int
    {
        return $this->validation->andesCertificateRequest
            ->certificateRequest
            ->company_id;
    }

    public function resourceData(): array
    {
        return [
            'validation_id'                  => $this->validation->id,
            'andes_certificate_request_id'   => $this->validation->andes_certificate_request_id,
            'validation_type'                => $this->validation->validation_type,
            'estado'                         => $this->validation->estado,
            'validated_at'                   => $this->validation->validated_at?->toIso8601String(),
        ];
    }
}

