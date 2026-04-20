<?php

namespace App\Andes\Enums;

enum AndesDocTypeEnum: int
{
    case CEDULA_CIUDADANIA = 1;
    case NIT               = 2;
    case CEDULA_EXTRANJERIA = 3;
    case PASAPORTE         = 6;

    public function label(): string
    {
        return match($this) {
            self::CEDULA_CIUDADANIA  => 'Cédula de Ciudadanía',
            self::NIT                => 'NIT',
            self::CEDULA_EXTRANJERIA => 'Cédula de Extranjería',
            self::PASAPORTE          => 'Pasaporte',
        };
    }
}

