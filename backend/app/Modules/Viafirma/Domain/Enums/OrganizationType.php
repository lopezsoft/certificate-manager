<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

/**
 * organizationType de Viafirma RA — sólo aplica a perfiles FE-PJ.
 *
 * Catálogo según PDF "Uso del API para perfiles PKCS#10 V1.1" (15/05/2026).
 */
enum OrganizationType: string
{
    case RM          = 'RM';           // Registro Mercantil
    case PROP        = 'PROP';         // Propiedad horizontal
    case RUNEOL      = 'RUNEOL';       // Registro Único Nacional de Entidades Operadoras de Libranza
    case RNT         = 'RNT';          // Registro Nacional de Turismo
    case ESAL        = 'ESAL';         // Entidades Sin Ánimo de Lucro
    case ESOL        = 'ESOL';         // Entidades del sector solidario
    case JUEGOS      = 'JUEGOS';       // Operadores de juegos
    case EXTRANJERAS = 'EXTRANJERAS';  // Empresas extranjeras

    public function label(): string
    {
        return match ($this) {
            self::RM          => 'Registro Mercantil',
            self::PROP        => 'Propiedad Horizontal',
            self::RUNEOL      => 'RUNEOL (Libranza)',
            self::RNT         => 'Registro Nacional de Turismo',
            self::ESAL        => 'Entidad Sin Ánimo de Lucro',
            self::ESOL        => 'Entidad del Sector Solidario',
            self::JUEGOS      => 'Operadores de Juegos',
            self::EXTRANJERAS => 'Empresa Extranjera',
        };
    }
}

