/**
 * Interfaces para las estadísticas de solicitudes de certificados por empresa.
 * Endpoint: GET /api/v1/certificate-request/stats/{companyId}
 */

export interface CertificateRequestStats {
  company_id: number;
  company_name: string;
  has_agreement: boolean;
  grand_total: number;
  quota: StatsQuota;
  data: YearlyStats[];
}

export interface StatsQuota {
  postpaid?: PostpaidQuota;
  prepaid_items_available: number;
  has_quota: boolean;
}

export interface PostpaidQuota {
  allocated: number;
  used: number;
  remaining: number;
  expires_at: string;
  status: string;
}

export interface YearlyStats {
  year: number;
  total: number;
  statuses: { [key: string]: number };
}
