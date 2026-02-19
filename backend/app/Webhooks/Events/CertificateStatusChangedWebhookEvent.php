<?php

namespace App\Webhooks\Events;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class CertificateStatusChangedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly int    $certificateRequestId,
        private readonly int    $companyId,
        private readonly string $previousStatus,
        private readonly string $newStatus,
        private readonly int    $userId,
        private readonly ?string $comment = null,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::CERTIFICATE_STATUS_CHANGED;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function resourceData(): array
    {
        return [
            'id'              => $this->certificateRequestId,
            'previous_status' => $this->previousStatus,
            'new_status'      => $this->newStatus,
            'user_id'         => $this->userId,
            'comment'         => $this->comment,
        ];
    }
}
