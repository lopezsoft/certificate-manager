/**
 * Interfaces para los endpoints GET /pricing
 *
 * PricingTier: representa una franja tarifaria (RANGO_1, RANGO_2, RANGO_3).
 * PricingCalculation: resultado del cálculo de precio exacto con impuestos.
 */

export interface PricingTier {
  tier: string;
  min: number;
  max: number | null;
  price_1yr: number;
  price_2yr: number;
}

export interface PricingCalculation {
  tier: string;
  unit_price: number;
  quantity: number;
  vigencia: number;
  subtotal: number;
  tax_amount: number;
  total: number;
  currency: string;
}
