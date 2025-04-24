<?php

namespace App\Common;
use Lopezsoft\VerificationDigit\VerificationDigit as VerificationDigitLocal;
class VerificationDigit
{
    public static function getDigit(int $Number = 0): int
    {
        return VerificationDigitLocal::getDigit($Number);
    }
}
