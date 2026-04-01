<?php

namespace App\Webhooks\Events;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class CertificateDeletedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly int    $certificateRequestId,
        private readonly int    $companyId,
        private readonly string $dni,
        private readonly string $companyName,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::CERTIFICATE_DELETED;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function resourceData(): array
    {
        return [
            'id'           => $this->certificateRequestId,
            'dni'          => $this->dni,
            'company_name' => $this->companyName,
        ];
    }
}
