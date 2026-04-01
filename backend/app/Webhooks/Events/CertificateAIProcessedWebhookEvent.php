<?php

namespace App\Webhooks\Events;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class CertificateAIProcessedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly int   $certificateRequestId,
        private readonly int   $companyId,
        private readonly int   $fileId,
        private readonly array $aiResults,
        private readonly float $processingTime,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::CERTIFICATE_AI_PROCESSED;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function resourceData(): array
    {
        return [
            'id'              => $this->certificateRequestId,
            'file_id'         => $this->fileId,
            'processing_time' => $this->processingTime,
            'overall_valid'   => $this->aiResults['overall_valid'] ?? null,
            'document_type'   => $this->aiResults['document_type'] ?? null,
        ];
    }
}
