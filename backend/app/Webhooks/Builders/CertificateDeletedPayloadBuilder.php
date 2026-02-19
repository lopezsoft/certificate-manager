<?php

namespace App\Webhooks\Builders;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Support\Str;

class CertificateDeletedPayloadBuilder implements WebhookPayloadBuilderContract
{
    public function supports(): string
    {
        return WebhookEventType::CERTIFICATE_DELETED;
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
                'dni'                    => $data['dni'],
                'company_name'           => $data['company_name'],
            ],
        ];
    }
}
