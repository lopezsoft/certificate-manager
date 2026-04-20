<?php

namespace App\Quotas\Enums;

enum QuotaStatusEnum: string
{
    case ACTIVE    = 'ACTIVE';
    case EXHAUSTED = 'EXHAUSTED';
    case EXPIRED   = 'EXPIRED';

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }
}

