/**
 * Representa un endpoint webhook registrado por el usuario.
 * Alineado con la respuesta real del backend (WebhookEndpointResource).
 */
export interface WebhookEndpoint {
  id: number;
  name: string;
  url: string;
  description: string | null;
  events: string[];
  is_active: boolean;
  last_triggered_at: string | null;
  failure_count: number;
  created_at: string;
  updated_at: string;
}

/**
 * Payload para crear un nuevo webhook.
 * POST /webhooks
 */
export interface WebhookCreateRequest {
  name: string;
  url: string;
  description?: string;
  events: string[];
}

/**
 * Payload para actualizar un webhook existente.
 * PUT /webhooks/{id}
 */
export interface WebhookUpdateRequest {
  name?: string;
  url?: string;
  description?: string;
  is_active?: boolean;
  events?: string[];
}

/**
 * Respuesta del servidor al rotar el secreto de un webhook.
 * dataRecords: { endpoint: WebhookEndpoint, secret: string }
 */
export interface WebhookRotateSecretResponse {
  endpoint: WebhookEndpoint;
  secret: string;
}

