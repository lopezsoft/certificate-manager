<?php

namespace Tests\Unit\Common;

use App\Common\VerificationDigit;
use Tests\TestCase;

/**
 * Tests unitarios para VerificationDigit.
 * Cálculo puro — sin base de datos ni dependencias externas.
 */
class VerificationDigitTest extends TestCase
{
    /**
     * Pares NIT → DV conocidos y verificados con la DIAN.
     */
    public static function nitDvProvider(): array
    {
        return [
            'NIT 900455420 → DV 8' => [900455420, 8],
            'NIT 800197268 → DV 4' => [800197268, 4],
            'NIT 830085426 → DV 1' => [830085426, 1],
            'NIT 900775649 → DV 1' => [900775649, 1],
            'NIT 901347696 → DV 4' => [901347696, 4],
        ];
    }

    /**
     * @dataProvider nitDvProvider
     */
    public function test_calcula_digito_verificador_correctamente(int $nit, int $dvEsperado): void
    {
        $resultado = VerificationDigit::getDigit($nit);

        $this->assertSame($dvEsperado, $resultado);
    }

    public function test_retorna_entero(): void
    {
        $resultado = VerificationDigit::getDigit(900455420);

        $this->assertIsInt($resultado);
    }

    public function test_dv_esta_en_rango_valido(): void
    {
        $resultado = VerificationDigit::getDigit(900455420);

        $this->assertGreaterThanOrEqual(0, $resultado);
        $this->assertLessThanOrEqual(9, $resultado);
    }
}
