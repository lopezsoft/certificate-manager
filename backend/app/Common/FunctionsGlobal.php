<?php

namespace App\Common;

use App\Http\Controllers\Controller;
use Lopezsoft\NumbersToLetters\NumbersToLetters;
class FunctionsGlobal extends Controller
{

    public static function totalDecimals(string $amount): int
    {
        $result = 0;
        if (strlen($amount) > 0) {
            $value = substr($amount, strpos($amount, ".") + 1);
            for ($i = 0; $i < strlen($value); $i++) {
                $n  = substr($value, $i, 1);
                if (intval($n) > 0) {
                    $result += 1;
                }
            }
        }
        return $result;
    }

    public static function getNumbersToLetters(float $number, string $money = 'PESOS', string $money2 = 'CENTAVOS'): string
    {
        return (new NumbersToLetters)->getNumbersToLetters($number, $money, $money2);
    }

     public static function getDigitVerification(int $Number = 0): \Illuminate\Http\JsonResponse
     {
        $value      = VerificationDigit::getDigit($Number);
        if ($Number >= 0) {
            return response()->json([
                'message'   => 'Digito de verificación generado con éxito.',
                'success'   => true,
                'dv'        => $value
            ]);

        }else{
            return response()->json([
                'message'   => 'No es posible generar el digito de verificación.',
                'success'   => false,
                'dv'        => $value,
            ], 500);
        }
     }
}
