<?php

namespace App\Webhooks\Enums;

class WebhookEventType
{
    const CERTIFICATE_CREATED        = 'certificate_request.created';
    const CERTIFICATE_STATUS_CHANGED = 'certificate_request.status_changed';
    const CERTIFICATE_AI_PROCESSED   = 'certificate_request.ai_processed';
    const CERTIFICATE_FILE_UPLOADED  = 'certificate_request.file_uploaded';
    const CERTIFICATE_DELETED        = 'certificate_request.deleted';
    const CERTIFICATE_EXPIRING       = 'certificate.expiring';

    public static function all(): array
    {
        return [
            self::CERTIFICATE_CREATED,
            self::CERTIFICATE_STATUS_CHANGED,
            self::CERTIFICATE_AI_PROCESSED,
            self::CERTIFICATE_FILE_UPLOADED,
            self::CERTIFICATE_DELETED,
            self::CERTIFICATE_EXPIRING,
        ];
    }
}
