<?php

namespace App\Services;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateOrder;
use App\Models\CertificateRequest;
use App\Models\PricingTier;
use Illuminate\Support\Facades\Log;

/**
 * PricingService
 *
 * Calcula el precio de certificados según el volumen basándose en la tabla pricing_tiers.
 *
 * Para user_type_id IN (3, 4) — "Arrendamiento en Servidor" y "Partner" —
 * el rango se determina usando el mayor entre:
 *   - Total de emisiones del año anterior
 *   - Total de compras del año en curso + compra actual
 *
 * Para otros user_type_id, se usa la cantidad de la compra actual directamente.
 */
class PricingService
{
    /** user_type_id que aplican la regla de volumen histórico */
    private const VOLUME_BASED_USER_TYPES = [3, 4];

    /**
     * Calcula el precio total para una compra.
     *
     * @param int $quantity      Cantidad de certificados (≥ 1)
     * @param int $vigenciaYears Vigencia en años (1 o 2)
     * @param int $userTypeId    Tipo de usuario (determina catálogo de precios)
     * @param int $companyId     Empresa compradora (para consultar historial de emisiones)
     *
     * @return array{tier: string, unit_price: int, subtotal: float, tax_amount: float, total: float, quantity: int, vigencia: int, effective_quantity: int}
     *
     * @throws \InvalidArgumentException si los parámetros son inválidos
     */
    public function calculatePrice(int $quantity, int $vigenciaYears, int $userTypeId, int $companyId): array
    {
        $this->validateInputs($quantity, $vigenciaYears);

        $effectiveQty = $this->resolveEffectiveQuantity($quantity, $userTypeId, $companyId);
        $tierModel    = $this->getTierModelForQuantity($effectiveQty, $userTypeId);
        $unitPrice    = (int) $tierModel->getPriceForVigencia($vigenciaYears);

        // Facturar por cantidad real, no por la efectiva
        $subtotal  = $unitPrice * $quantity;
        $taxPct    = 1 + (int) config('wompi.tax_percentage', 19) / 100;
        $taxAmount = (float) $subtotal - round($subtotal / $taxPct);
        $total     = $subtotal; // IVA incluido en el precio

        return [
            'tier'               => $tierModel->code,
            'unit_price'         => $unitPrice,
            'quantity'           => $quantity,
            'vigencia'           => $vigenciaYears,
            'subtotal'           => $subtotal - $taxAmount,
            'tax_amount'         => $taxAmount,
            'total'              => $total,
            'currency'           => config('wompi.currency', 'COP'),
            'effective_quantity' => $effectiveQty,
        ];
    }

    /**
     * Devuelve todos los rangos de precio con su tabla completa desde la base de datos.
     * Útil para el endpoint privado GET /v1/pricing.
     */
    public function getActiveTiers(int $userTypeId): array
    {
        return PricingTier::where('is_active', true)
            ->where('user_type_id', $userTypeId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($tier) => [
                'tier'      => $tier->code,
                'name'      => $tier->name,
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
     * Determina la cantidad efectiva para buscar el tier correcto.
     *
     * Para user_type_id 3 y 4:
     *   - Si el cliente emitió certificados el año anterior:
     *     MAX(total_año_pasado, compras_año_curso + compra_actual)
     *   - Si NO emitió el año anterior:
     *     compras_año_curso + compra_actual
     *
     * Para otros user_type_id: retorna la cantidad de compra actual.
     */
    private function resolveEffectiveQuantity(int $currentPurchase, int $userTypeId, int $companyId): int
    {
        if (! in_array($userTypeId, self::VOLUME_BASED_USER_TYPES, true)) {
            return $currentPurchase;
        }

        $currentYear  = (int) now()->format('Y');
        $previousYear = $currentYear - 1;

        // Emisiones del año anterior (certificados realmente emitidos)
        $lastYearEmissions = CertificateRequest::where('company_id', $companyId)
            ->whereIn('request_status', CertificateRequestStatusEnum::issuedStatuses())
            ->whereYear('updated_at', $previousYear)
            ->count();

        // Compras pagadas del año en curso
        $currentYearPurchases = (int) CertificateOrder::where('company_id', $companyId)
            ->where('status', 'PAID')
            ->whereYear('created_at', $currentYear)
            ->sum('quantity');

        $currentTotal = $currentYearPurchases + $currentPurchase;

        if ($lastYearEmissions > 0) {
            $effectiveQty = max($lastYearEmissions, $currentTotal);
        } else {
            $effectiveQty = $currentTotal;
        }

        Log::info('[PRICING] Cantidad efectiva calculada.', [
            'company_id'            => $companyId,
            'user_type_id'          => $userTypeId,
            'current_purchase'      => $currentPurchase,
            'last_year_emissions'   => $lastYearEmissions,
            'current_year_purchases'=> $currentYearPurchases,
            'effective_quantity'     => $effectiveQty,
        ]);

        return max($effectiveQty, 1); // Mínimo 1 para evitar tier vacío
    }

    /**
     * Devuelve el modelo PricingTier correspondiente a la cantidad.
     */
    private function getTierModelForQuantity(int $quantity, int $userTypeId): PricingTier
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La cantidad de certificados debe ser al menos 1.');
        }

        $tier = PricingTier::where('is_active', true)
            ->where('user_type_id', $userTypeId)
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
