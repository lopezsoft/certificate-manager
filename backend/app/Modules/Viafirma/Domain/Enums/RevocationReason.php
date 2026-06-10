<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

/**
 * Motivos de revocación de certificado aceptados por Viafirma RA Colombia.
 *
 * @see POST /request/revoke/code/{revokingCode}  Body: { "revocationReason": <value> }
 */
enum RevocationReason: int
{
    case UNSPECIFIED              = 0;
    case KEY_COMPROMISE           = 1;
    case CA_COMPROMISE            = 2;
    case AFFILIATION_CHANGED      = 3;
    case SUPERSEDED               = 4;
    case CESSATION_OF_OPERATION   = 5;
    case PRIVILEGE_WITHDRAWN      = 9;
    case AA_COMPROMISE            = 10;

    public function label(): string
    {
        return match ($this) {
            self::UNSPECIFIED            => 'Sin especificar',
            self::KEY_COMPROMISE         => 'Clave comprometida',
            self::CA_COMPROMISE          => 'Autoridad de certificación comprometida',
            self::AFFILIATION_CHANGED    => 'Ha cambiado la afiliación',
            self::SUPERSEDED             => 'Sustitución',
            self::CESSATION_OF_OPERATION => 'Cese de operaciones',
            self::PRIVILEGE_WITHDRAWN    => 'Permisos retirados',
            self::AA_COMPROMISE          => 'AA comprometida',
        };
    }

    /** Retorna todos los valores enteros válidos para validación de FormRequest. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
