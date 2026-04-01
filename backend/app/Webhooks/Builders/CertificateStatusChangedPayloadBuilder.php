<?php

namespace App\Webhooks\Builders;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Support\Str;

class CertificateStatusChangedPayloadBuilder implements WebhookPayloadBuilderContract
{
    public function supports(): string
    {
        return WebhookEventType::CERTIFICATE_STATUS_CHANGED;
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
                'previous_status'        => $data['previous_status'],
                'new_status'             => $data['new_status'],
                'changed_by_user_id'     => $data['user_id'] ?? null,
                'comment'                => $data['comment'] ?? null,
            ],
        ];
    }
}
