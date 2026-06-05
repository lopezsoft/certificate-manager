/**
 * Registra un intento de entrega de un evento webhook.
 * Alineado con la respuesta real del backend.
 */
export interface WebhookDelivery {
  id: number;
  webhook_endpoint_id: number;
  event_type: string;
  payload: Record<string, any>;
  response_code: number | null;
  status: string;
  created_at: string;
  // Campos detallados adicionales (pueden ser nulos si no se almacenan)
  response_body?: string | Record<string, any> | null;
  response_headers?: Record<string, any> | null;
  request_headers?: Record<string, any> | null;
  error_message?: string | null;
}

/**
 * Respuesta paginada de entregas (estructura Laravel paginator).
 */
export interface WebhookDeliveriesResponse {
  data: WebhookDelivery[];
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

