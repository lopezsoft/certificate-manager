/**
 * Estado de salud de un endpoint webhook basado en el historial de entregas.
 */
export enum WebhookHealthStatus {
  HEALTHY = 'healthy',
  DEGRADED = 'degraded',
  FAILING = 'failing',
  UNKNOWN = 'unknown',
}

/** Etiquetas en español para WebhookHealthStatus */
export const WebhookHealthStatusLabel: Record<WebhookHealthStatus, string> = {
  [WebhookHealthStatus.HEALTHY]: 'Saludable',
  [WebhookHealthStatus.DEGRADED]: 'Degradado',
  [WebhookHealthStatus.FAILING]: 'Fallando',
  [WebhookHealthStatus.UNKNOWN]: 'Desconocido',
};

// ---------------------------------------------------------------------------

/**
 * Estado de un intento individual de entrega webhook.
 */
export enum WebhookDeliveryStatus {
  PENDING = 'pending',
  SUCCESS = 'success',
  FAILED = 'failed',
  RETRYING = 'retrying',
}

/** Etiquetas en español para WebhookDeliveryStatus */
export const WebhookDeliveryStatusLabel: Record<WebhookDeliveryStatus, string> = {
  [WebhookDeliveryStatus.PENDING]: 'Pendiente',
  [WebhookDeliveryStatus.SUCCESS]: 'Exitoso',
  [WebhookDeliveryStatus.FAILED]: 'Fallido',
  [WebhookDeliveryStatus.RETRYING]: 'Reintentando',
};

// ---------------------------------------------------------------------------

/**
 * Tipos de eventos que puede emitir el sistema hacia los webhooks.
 * El payload de certificate.status_changed usa los valores de DocumentStatusEnum.
 */
export const WEBHOOK_EVENT_TYPES: string[] = [
  'certificate.created',
  'certificate.status_changed',
  'certificate.signed',
  'certificate.rejected',
  'certificate.cancelled',
];
