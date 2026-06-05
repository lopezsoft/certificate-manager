import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { HttpResponsesService, DebugService } from '../../utils';
import {
  WebhookEndpoint,
  WebhookCreateRequest,
  WebhookUpdateRequest,
  WebhookRotateSecretResponse,
  WebhookDeliveriesResponse,
} from '../../interfaces';

@Injectable({
  providedIn: 'root',
})
export class WebhooksService {

  private readonly CTX = 'WebhooksService';

  constructor(
    private api: HttpResponsesService,
    private debug: DebugService,
  ) { }

  // ─── Endpoints ────────────────────────────────────────────────────────────

  /**
   * Obtiene todos los webhooks registrados por el usuario actual.
   */
  getAll(): Observable<WebhookEndpoint[]> {
    this.debug.log(this.CTX, 'getAll');
    return this.api.get('/webhooks') as any;
  }

  /**
   * Obtiene un webhook por su ID.
   */
  getById(id: number): Observable<WebhookEndpoint> {
    this.debug.log(this.CTX, 'getById', id);
    return this.api.get(`/webhooks/${id}`) as any;
  }

  /**
   * Obtiene la lista de eventos disponibles desde el backend.
   * GET /webhooks/events
   */
  getAvailableEvents(): Observable<string[]> {
    this.debug.log(this.CTX, 'getAvailableEvents');
    return this.api.get('/webhooks/events') as any;
  }

  /**
   * Crea un nuevo webhook.
   */
  create(payload: WebhookCreateRequest): Observable<WebhookEndpoint> {
    this.debug.log(this.CTX, 'create', payload);
    return this.api.post('/webhooks', payload) as any;
  }

  /**
   * Actualiza un webhook existente.
   */
  update(id: number, payload: WebhookUpdateRequest): Observable<WebhookEndpoint> {
    this.debug.log(this.CTX, 'update', id, payload);
    return this.api.put(`/webhooks/${id}`, payload) as any;
  }

  /**
   * Activa o desactiva un webhook (toggle is_active).
   */
  toggleActive(id: number, isActive: boolean): Observable<WebhookEndpoint> {
    this.debug.log(this.CTX, 'toggleActive', id, isActive);
    return this.api.put(`/webhooks/${id}`, { is_active: isActive }) as any;
  }

  /**
   * Elimina un webhook de forma permanente.
   */
  delete(id: number): Observable<any> {
    this.debug.log(this.CTX, 'delete', id);
    return this.api.delete(`/webhooks/${id}`);
  }

  /**
   * Rota el secreto compartido de un webhook.
   */
  rotateSecret(id: number): Observable<WebhookRotateSecretResponse> {
    this.debug.log(this.CTX, 'rotateSecret', id);
    return this.api.post(`/webhooks/${id}/rotate-secret`, {}) as any;
  }

  // ─── Deliveries ───────────────────────────────────────────────────────────

  /**
   * Obtiene el historial de entregas de un webhook.
   */
  getDeliveries(webhookId: number, params?: any): Observable<WebhookDeliveriesResponse> {
    this.debug.log(this.CTX, 'getDeliveries', webhookId, params);
    return this.api.get(`/webhooks/${webhookId}/deliveries`, params) as any;
  }

  /**
   * Reintenta el envío de una entrega fallida.
   */
  retryDelivery(webhookId: number, deliveryId: number): Observable<any> {
    this.debug.log(this.CTX, 'retryDelivery', webhookId, deliveryId);
    return this.api.post(`/webhooks/${webhookId}/deliveries/${deliveryId}/retry`, {});
  }
}
