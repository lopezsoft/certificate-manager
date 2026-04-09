<?php

namespace App\Webhooks\Builders;

use App\Enums\CertificateRequestStatusEnum;
use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Support\Str;

class CertificateCreatedPayloadBuilder implements WebhookPayloadBuilderContract
{
    public function supports(): string
    {
        return WebhookEventType::CERTIFICATE_CREATED;
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
                'company_name'           => $data['company_name'] ?? null,
                'dni'                    => $data['dni'] ?? null,
                'dv'                     => $data['dv'] ?? null,
                'request_status'         => $data['request_status'] ?? CertificateRequestStatusEnum::DRAFT->value,
                'legal_representative'   => $data['legal_representative'] ?? null,
            ],
        ];
    }
}
