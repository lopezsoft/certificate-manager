/**
 * Interfaces para los endpoints de órdenes y pagos
 *
 * POST /orders          → OrderCreateRequest / OrderResponse
 * POST /orders/{id}/pay → PaymentRequest / PaymentResponse
 * GET  /orders/{id}     → Order (detalle con estado)
 */

export interface OrderCreateRequest {
  quantity: number;
  vigencia: number;
}

export interface OrderResponse {
  order_id: number;
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
  id: number;
  quantity: number;
  vigencia: number;
  total_amount: number;
  currency: string;
  status: string;
  provider_reference: string;
  created_at: string;
  updated_at: string;
  created_at_formatted?: string;
  updated_at_formatted?: string;
}
