<?php

namespace App\Webhooks\Events;

use App\Andes\Models\AndesCertificateRequest;
use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class AndesCertificateEmittedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly AndesCertificateRequest $andesCertificateRequest,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::ANDES_CERTIFICATE_EMITTED;
    }

    public function companyId(): int
    {
        return $this->andesCertificateRequest->certificateRequest->company_id;
    }

    public function resourceData(): array
    {
        return [
            'andes_certificate_request_id' => $this->andesCertificateRequest->id,
            'certificate_request_id'       => $this->andesCertificateRequest->certificate_request_id,
            'andes_solicitud_id'           => $this->andesCertificateRequest->andes_solicitud_id,
            'certificate_serial'           => $this->andesCertificateRequest->certificate_serial,
            'tipo_cert'                    => $this->andesCertificateRequest->tipo_cert,
            'vigencia_cert'                => $this->andesCertificateRequest->vigencia_cert,
            'emitted_at'                   => $this->andesCertificateRequest->emitted_at?->toIso8601String(),
        ];
    }
}

