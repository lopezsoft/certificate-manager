<?php

namespace App\Quotas\Services;

/**
 * PricingService
 *
 * Calcula el precio de certificados según el volumen mensual.
 *
 * Tarifas LOPEZSOFT (al público):
 * | Nivel   | Volumen     | 1 Año     | 2 Años    |
 * |---------|-------------|-----------|-----------|
 * | RANGO_1 | 1-4 und/mes | $135,000  | $215,000  |
 * | RANGO_2 | 5-9 und/mes | $125,000  | $200,000  |
 * | RANGO_3 | 10+ und/mes | $115,000  | $185,000  |
 */
class PricingService
{
    /** Tabla de precios indexada por rango y vigencia (años) */
    private const PRICE_TABLE = [
        'RANGO_1' => ['min' => 1,  'max' => 4,     'prices' => [1 => 135_000, 2 => 215_000]],
        'RANGO_2' => ['min' => 5,  'max' => 9,     'prices' => [1 => 125_000, 2 => 200_000]],
        'RANGO_3' => ['min' => 10, 'max' => PHP_INT_MAX, 'prices' => [1 => 115_000, 2 => 185_000]],
    ];

    /**
     * Calcula el precio total para una compra.
     *
     * @param int $quantity     Cantidad de certificados (≥ 1)
     * @param int $vigenciaYears Vigencia en años (1 o 2)
     * @return array{tier: string, unit_price: int, subtotal: int, tax_amount: int, total: int, quantity: int, vigencia: int}
     *
     * @throws \InvalidArgumentException si los parámetros son inválidos
     */
    public function calculatePrice(int $quantity, int $vigenciaYears): array
    {
        $this->validateInputs($quantity, $vigenciaYears);

        $tier      = $this->getTierForQuantity($quantity);
        $unitPrice = self::PRICE_TABLE[$tier]['prices'][$vigenciaYears];
        $subtotal  = $unitPrice * $quantity;
        $taxPct    = (int) config('wompi.tax_percentage', 19);
        $taxAmount = (int) round($subtotal * ($taxPct / 100));
        $total     = $subtotal + $taxAmount;

        return [
            'tier'       => $tier,
            'unit_price' => $unitPrice,
            'quantity'   => $quantity,
            'vigencia'   => $vigenciaYears,
            'subtotal'   => $subtotal,
            'tax_amount' => $taxAmount,
            'total'      => $total,
            'currency'   => config('wompi.currency', 'COP'),
        ];
    }

    /**
     * Devuelve todos los rangos de precio con su tabla completa.
     * Útil para el endpoint público GET /v2/pricing.
     */
    public function getActiveTiers(): array
    {
        $result = [];
        foreach (self::PRICE_TABLE as $tier => $data) {
            $result[] = [
                'tier'      => $tier,
                'min'       => $data['min'],
                'max'       => $data['max'] === PHP_INT_MAX ? null : $data['max'],
                'price_1yr' => $data['prices'][1],
                'price_2yr' => $data['prices'][2],
            ];
        }
        return $result;
    }

    /**
     * Devuelve el nombre del tier (RANGO_1, RANGO_2, RANGO_3) para una cantidad.
     *
     * @throws \InvalidArgumentException si la cantidad es menor a 1
     */
    public function getTierForQuantity(int $quantity): string
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La cantidad de certificados debe ser al menos 1.');
        }

        foreach (self::PRICE_TABLE as $tier => $data) {
            if ($quantity >= $data['min'] && $quantity <= $data['max']) {
                return $tier;
            }
        }

        // Fallback (no debería ocurrir si PHP_INT_MAX está correctamente definido)
        return 'RANGO_3';
    }

    private function validateInputs(int $quantity, int $vigenciaYears): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La cantidad debe ser al menos 1.');
        }

        if (! in_array($vigenciaYears, [1, 2], true)) {
            throw new \InvalidArgumentException('La vigencia debe ser 1 o 2 años.');
        }
    }
}

