/**
 * Interfaces para los endpoints de órdenes y pagos
 *
 * POST /orders              → OrderCreateRequest / OrderResponse
 * POST /orders/{uuid}/pay   → PaymentRequest / PaymentResponse
 * POST /orders/{uuid}/retry → OrderResponse
 * GET  /orders/{uuid}       → Order (detalle con estado)
 * DELETE /orders/{uuid}     → void
 */

export interface OrderCreateRequest {
  quantity: number;
  vigencia: number;
}

export interface OrderResponse {
  order_id: string;         // UUID
  total_amount: number;
  currency: string;
  provider_reference: string;
  acceptance_token: string;
  acceptance_url: string;
  integrity_hash: string;
}

export interface PaymentRequest {
  payment_source_id: number;
  acceptance_token: string;
  payment_method: string;
  installments: number;
}

export interface PaymentResponse {
  transaction_id: number;
  status: string;
  provider_transaction_id: string;
}

export interface Order {
  uuid: string;
  quantity: number;
  vigencia: number;
  unit_price?: number;
  subtotal?: number;
  tax_amount?: number;
  total_amount: number;
  currency: string;
  status: string;
  payment_provider?: string;
  payment_method?: string;
  provider_reference: string;
  created_at: string;
  updated_at: string;
  created_at_formatted?: string;
  updated_at_formatted?: string;
}
