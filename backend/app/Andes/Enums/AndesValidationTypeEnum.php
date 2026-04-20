<?php

namespace App\Andes\Enums;

enum AndesValidationTypeEnum: string
{
    case OTP        = 'PhoneSelection';
    case CUESTIONARIO = 'ShowExam';

    public function label(): string
    {
        return match($this) {
            self::OTP          => 'Validación OTP (SMS/Voz)',
            self::CUESTIONARIO => 'Validación por Cuestionario',
        };
    }
}

