<?php

namespace App\Webhooks\Builders;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Support\Str;

class CertificateAIProcessedPayloadBuilder implements WebhookPayloadBuilderContract
{
    public function supports(): string
    {
        return WebhookEventType::CERTIFICATE_AI_PROCESSED;
    }

    public function build(WebhookEventContract $event): array
    {
        $data = $event->resourceData();

        return [
            'id'         => 'wh_' . Str::ulid(),
            'event'      => $this->supports(),
            'created_at' => now()->toIso8601String(),
            'data'       => [
                'certificate_request_id' => $data['id'],
                'company_id'             => $event->companyId(),
                'file_id'                => $data['file_id'],
                'processing_time_ms'     => $data['processing_time'],
                'overall_valid'          => $data['overall_valid'],
                'document_type'          => $data['document_type'],
            ],
        ];
    }
}
