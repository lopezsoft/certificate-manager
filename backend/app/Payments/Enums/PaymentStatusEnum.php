<?php

namespace App\Payments\Enums;

enum PaymentStatusEnum: string
{
    case PENDING  = 'PENDING';
    case APPROVED = 'APPROVED';
    case DECLINED = 'DECLINED';
    case VOIDED   = 'VOIDED';
    case ERROR    = 'ERROR';

    public function isSuccessful(): bool
    {
        return $this === self::APPROVED;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::APPROVED, self::DECLINED, self::VOIDED, self::ERROR]);
    }
}

