<?php

namespace App\Andes\Enums;

enum AndesFormatEnum: int
{
    case TOKEN_FISICO   = 2;
    case PKCS10         = 3;
    case TOKEN_VIRTUAL  = 4;

    public function label(): string
    {
        return match($this) {
            self::TOKEN_FISICO  => 'Token Físico',
            self::PKCS10        => 'PKCS10 (Software)',
            self::TOKEN_VIRTUAL => 'Token Virtual',
        };
    }
}

