<?php

namespace App\Webhooks\Builders;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Support\Str;

class CertificateFileUploadedPayloadBuilder implements WebhookPayloadBuilderContract
{
    public function supports(): string
    {
        return WebhookEventType::CERTIFICATE_FILE_UPLOADED;
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
                'file_name'              => $data['file_name'],
                'document_type'          => $data['document_type'],
            ],
        ];
    }
}
