<?php

namespace App\Andes\Enums;

enum AndesVigenciaEnum: int
{
    case UN_DIA      = 15;
    case UN_ANIO     = 3;
    case CATORCE_MESES = 17;
    case DOS_ANIOS   = 4;

    public function label(): string
    {
        return match($this) {
            self::UN_DIA          => '1 Día',
            self::UN_ANIO         => '1 Año',
            self::CATORCE_MESES   => '14 Meses',
            self::DOS_ANIOS       => '2 Años',
        };
    }

    public static function fromYears(int $years): self
    {
        return match($years) {
            1       => self::UN_ANIO,
            2       => self::DOS_ANIOS,
            default => throw new \InvalidArgumentException("Vigencia en años no soportada: {$years}"),
        };
    }
}

