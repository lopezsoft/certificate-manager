import { WebhookHealthStatus } from '../common/enums/WebhookStatus';

/**
 * Representa un endpoint webhook registrado por el usuario.
 */
export interface WebhookEndpoint {
  id: number;
  url: string;
  description: string | null;
  secret: string | null;
  is_active: boolean;
  health_status: WebhookHealthStatus;
  events: string[];
  created_at: string;
  updated_at: string;
  last_delivery_at: string | null;
  delivery_success_count: number;
  delivery_failure_count: number;
}

/**
 * Payload para crear un nuevo webhook.
 */
export interface WebhookCreateRequest {
  url: string;
  description?: string;
  secret?: string;
  events: string[];
}

/**
 * Payload para actualizar un webhook existente.
 */
export interface WebhookUpdateRequest {
  url?: string;
  description?: string;
  is_active?: boolean;
  events?: string[];
}

/**
 * Respuesta del servidor al rotar el secreto de un webhook.
 */
export interface WebhookRotateSecretResponse {
  secret: string;
}
