import { WebhookDeliveryStatus } from '../common/enums/WebhookStatus';

/**
 * Registra un intento de entrega de un evento webhook.
 */
export interface WebhookDelivery {
  id: number;
  webhook_endpoint_id: number;
  event_type: string;
  payload: Record<string, any>;
  response_status: number | null;
  response_body: string | null;
  response_time_ms: number | null;
  attempt_count: number;
  status: WebhookDeliveryStatus;
  next_retry_at: string | null;
  delivered_at: string | null;
  created_at: string;
}

/**
 * Respuesta paginada de entregas.
 */
export interface WebhookDeliveriesResponse {
  data: WebhookDelivery[];
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}
