<?php

namespace App\Payments\Enums;

enum PaymentMethodEnum: string
{
    case CARD         = 'CARD';
    case NEQUI        = 'NEQUI';
    case PSE          = 'PSE';
    case BANCOLOMBIA  = 'BANCOLOMBIA_TRANSFER';

    public function label(): string
    {
        return match($this) {
            self::CARD        => 'Tarjeta de Crédito/Débito',
            self::NEQUI       => 'Nequi',
            self::PSE         => 'PSE',
            self::BANCOLOMBIA => 'Bancolombia',
        };
    }
}

