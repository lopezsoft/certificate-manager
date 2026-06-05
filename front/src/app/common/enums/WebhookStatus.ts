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
 * Representación de un tipo de evento webhook con nombre legible.
 */
export interface WebhookEventType {
  value: string;
  label: string;
  description: string;
  icon: string;
  group: string;
}

/**
 * Tipos de eventos que puede emitir el sistema hacia los webhooks.
 * Alineados con WebhookEventType.php del backend.
 *
 * Nota: El frontend también consume GET /webhooks/events para obtener
 * la lista dinámica. Este mapa se usa como fallback y para etiquetas.
 */
export const WEBHOOK_EVENT_TYPES: WebhookEventType[] = [
  // ── Solicitudes de certificado ──────────────────────────────────────────
  {
    value: 'certificate_request.created',
    label: 'Solicitud creada',
    description: 'Se dispara cuando se crea una nueva solicitud de certificado.',
    icon: 'plus-circle',
    group: 'Solicitudes',
  },
  {
    value: 'certificate_request.status_changed',
    label: 'Estado de solicitud actualizado',
    description: 'Se dispara cuando cambia el estado de una solicitud.',
    icon: 'refresh-cw',
    group: 'Solicitudes',
  },
  {
    value: 'certificate_request.ai_processed',
    label: 'Procesado por IA',
    description: 'Se dispara cuando la IA termina de procesar los documentos adjuntos.',
    icon: 'cpu',
    group: 'Solicitudes',
  },
  {
    value: 'certificate_request.file_uploaded',
    label: 'Archivo subido',
    description: 'Se dispara cuando se sube un archivo a una solicitud.',
    icon: 'upload',
    group: 'Solicitudes',
  },
  {
    value: 'certificate_request.deleted',
    label: 'Solicitud eliminada',
    description: 'Se dispara cuando se elimina una solicitud.',
    icon: 'trash-2',
    group: 'Solicitudes',
  },

  // ── Certificados ────────────────────────────────────────────────────────
  {
    value: 'certificate.expiring',
    label: 'Certificado por vencer',
    description: 'Se dispara cuando un certificado está próximo a vencer.',
    icon: 'alert-triangle',
    group: 'Certificados',
  },

  // ── Pagos ───────────────────────────────────────────────────────────────
  {
    value: 'payment.approved',
    label: 'Pago aprobado',
    description: 'Se dispara cuando un pago WOMPI es aprobado exitosamente.',
    icon: 'check-circle',
    group: 'Pagos',
  },
  {
    value: 'payment.failed',
    label: 'Pago fallido',
    description: 'Se dispara cuando un pago WOMPI falla.',
    icon: 'x-circle',
    group: 'Pagos',
  },
];

/** Valores técnicos como string[] para compatibilidad con el backend */
export const WEBHOOK_EVENT_TYPE_VALUES: string[] =
  WEBHOOK_EVENT_TYPES.map(e => e.value);

/**
 * Mapa de lookup: valor técnico → etiqueta legible.
 * Usado por el pipe `webhookEvent` y el listado de webhooks.
 */
export const WebhookEventLabel: Record<string, string> = WEBHOOK_EVENT_TYPES.reduce(
  (acc, e) => ({ ...acc, [e.value]: e.label }),
  {} as Record<string, string>,
);

