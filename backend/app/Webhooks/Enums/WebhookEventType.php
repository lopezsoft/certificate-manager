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

    // ANDES SCD events
    const ANDES_IDENTITY_VALIDATED   = 'andes.identity_validated';
    const ANDES_CERTIFICATE_EMITTED  = 'andes.certificate_emitted';

    // Payment events
    const PAYMENT_APPROVED           = 'payment.approved';
    const PAYMENT_FAILED             = 'payment.failed';

    public static function all(): array
    {
        return [
            self::CERTIFICATE_CREATED,
            self::CERTIFICATE_STATUS_CHANGED,
            self::CERTIFICATE_AI_PROCESSED,
            self::CERTIFICATE_FILE_UPLOADED,
            self::CERTIFICATE_DELETED,
            self::CERTIFICATE_EXPIRING,
            self::ANDES_IDENTITY_VALIDATED,
            self::ANDES_CERTIFICATE_EMITTED,
            self::PAYMENT_APPROVED,
            self::PAYMENT_FAILED,
        ];
    }
}
