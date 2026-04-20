<?php

namespace App\Andes\Enums;

enum AndesCertTypeEnum: int
{
    case FACTURACION_ELECTRONICA_JURIDICA = 10;
    case FACTURACION_ELECTRONICA_NATURAL  = 11;

    public function label(): string
    {
        return match($this) {
            self::FACTURACION_ELECTRONICA_JURIDICA => 'Facturación Electrónica Persona Jurídica',
            self::FACTURACION_ELECTRONICA_NATURAL  => 'Facturación Electrónica Persona Natural',
        };
    }
}

