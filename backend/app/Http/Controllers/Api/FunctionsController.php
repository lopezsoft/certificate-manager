<?php

namespace App\Http\Controllers\Api;

use App\Common\FunctionsGlobal;
use App\Http\Controllers\Controller;

class FunctionsController extends Controller
{

    public function getNumbersToLetters(float $number, string $money = 'PESOS', string $money2 = 'CENTAVOS'): string
    {
        return FunctionsGlobal::getNumbersToLetters($number, $money, $money2);
    }

     public function getDigitVerification(int $Number = 0): \Illuminate\Http\JsonResponse
     {
        return FunctionsGlobal::getDigitVerification($Number);
     }
}
