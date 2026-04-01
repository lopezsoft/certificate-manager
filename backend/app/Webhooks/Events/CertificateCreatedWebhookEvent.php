<?php

namespace App\Webhooks\Events;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class CertificateCreatedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly int   $certificateRequestId,
        private readonly int   $companyId,
        private readonly array $data,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::CERTIFICATE_CREATED;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function resourceData(): array
    {
        return array_merge(['id' => $this->certificateRequestId], $this->data);
    }
}
