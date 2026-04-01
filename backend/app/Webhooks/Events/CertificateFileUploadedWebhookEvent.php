<?php

namespace App\Webhooks\Events;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class CertificateFileUploadedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly int    $certificateRequestId,
        private readonly int    $companyId,
        private readonly int    $fileId,
        private readonly string $fileName,
        private readonly string $documentType,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::CERTIFICATE_FILE_UPLOADED;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function resourceData(): array
    {
        return [
            'id'                     => $this->certificateRequestId,
            'file_id'                => $this->fileId,
            'file_name'              => $this->fileName,
            'document_type'          => $this->documentType,
        ];
    }
}
