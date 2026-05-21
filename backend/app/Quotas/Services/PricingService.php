<?php

namespace App\Quotas\Services;

use App\Models\PricingTier;

/**
 * PricingService
 *
 * Calcula el precio de certificados según el volumen mensual basándose en la tabla pricing_tiers.
 */
class PricingService
{
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

        $tierModel = $this->getTierModelForQuantity($quantity);
        $unitPrice = (int) $tierModel->getPriceForVigencia($vigenciaYears);
        
        $subtotal  = $unitPrice * $quantity;
        $taxPct    = (int) config('wompi.tax_percentage', 19);
        $taxAmount = (int) round($subtotal * ($taxPct / 100));
        $total     = $subtotal + $taxAmount;

        return [
            'tier'       => $tierModel->code,
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
     * Devuelve todos los rangos de precio con su tabla completa desde la base de datos.
     * Útil para el endpoint privado GET /v1/pricing.
     */
    public function getActiveTiers(): array
    {
        return PricingTier::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($tier) => [
                'tier'      => $tier->code,
                'min'       => $tier->min_quantity,
                'max'       => $tier->max_quantity,
                'price_1yr' => (int) $tier->price_1yr,
                'price_2yr' => (int) $tier->price_2yr,
            ])
            ->toArray();
    }

    /**
     * Devuelve el código del tier (ej. RANGO_1) para una cantidad.
     *
     * @throws \InvalidArgumentException si la cantidad es menor a 1
     */
    public function getTierForQuantity(int $quantity): string
    {
        return $this->getTierModelForQuantity($quantity)->code;
    }

    /**
     * Devuelve el modelo PricingTier correspondiente a la cantidad.
     */
    private function getTierModelForQuantity(int $quantity): PricingTier
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La cantidad de certificados debe ser al menos 1.');
        }

        $tier = PricingTier::where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($query) use ($quantity) {
                $query->where('max_quantity', '>=', $quantity)
                      ->orWhereNull('max_quantity');
            })
            ->first();

        if (! $tier) {
            throw new \InvalidArgumentException("No se encontró un rango de precios configurado para la cantidad {$quantity}.");
        }

        return $tier;
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

