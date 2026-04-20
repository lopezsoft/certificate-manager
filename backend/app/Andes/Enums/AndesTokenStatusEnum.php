<?php

namespace App\Andes\Enums;

enum AndesTokenStatusEnum: int
{
    case NO_ENCONTRADO = -1;
    case EN_CURSO      = 0;
    case VALIDADO      = 1;
    case FALLIDO       = 2;

    public function label(): string
    {
        return match($this) {
            self::NO_ENCONTRADO => 'Token no encontrado',
            self::EN_CURSO      => 'Validación en curso',
            self::VALIDADO      => 'Identidad validada',
            self::FALLIDO       => 'Validación fallida',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::VALIDADO;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::VALIDADO, self::FALLIDO, self::NO_ENCONTRADO]);
    }
}

