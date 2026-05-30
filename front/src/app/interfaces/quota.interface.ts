/**
 * Interfaces para el endpoint GET /quota/status
 *
 * Representan el estado de cupos de certificados disponibles
 * para la empresa del usuario autenticado.
 */

export interface PostpaidQuota {
  allocated: number;
  used: number;
  remaining: number;
  expires_at: string;
  status: string;
}

export interface QuotaStatus {
  has_quota: boolean;
  prepaid_items_available: number;
  postpaid: PostpaidQuota | null;
}
