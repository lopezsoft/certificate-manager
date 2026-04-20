<?php

namespace App\Quotas\Enums;

enum OrderStatusEnum: string
{
    case PENDING  = 'PENDING';
    case PAID     = 'PAID';
    case FAILED   = 'FAILED';
    case REFUNDED = 'REFUNDED';

    public function isSuccessful(): bool
    {
        return $this === self::PAID;
    }
}

