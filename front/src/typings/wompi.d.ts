/**
 * Declaración de tipo para el widget de checkout de Wompi.
 * El script se carga globalmente desde https://checkout.wompi.co/widget.js
 *
 * @see https://docs.wompi.co/docs/en/widget-checkout
 */

interface WompiWidgetConfig {
  /** Moneda de la transacción (ej: 'COP') */
  currency: string;
  /** Monto en centavos (ej: 5200000 = $52,000 COP) */
  amountInCents: number;
  /** Referencia única de la orden */
  reference: string;
  /** Llave pública de Wompi (pub_test_... o pub_prod_...) */
  publicKey: string;
  /** Hash de integridad para verificación */
  signature?: { integrity: string };
  /** URL de redirección después del pago */
  redirectUrl?: string;
  /** Token de aceptación de términos */
  customerData?: { legalId?: string; legalIdType?: string };
}

interface WompiTransactionResult {
  transaction: {
    id: string;
    status: string;
    reference: string;
    amountInCents: number;
    currency: string;
    paymentMethodType: string;
  };
}

declare class WidgetCheckout {
  constructor(config: WompiWidgetConfig);
  open(callback: (result: WompiTransactionResult) => void): void;
}
