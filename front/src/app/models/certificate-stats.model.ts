/**
 * Interfaces para las estadísticas de solicitudes de certificados por empresa.
 * Endpoint: GET /api/v1/certificate-request/stats/{companyId}
 */

export interface CertificateRequestStats {
  company_id: number;
  company_name: string;
  grand_total: number;
  data: YearlyStats[];
}

export interface YearlyStats {
  year: number;
  total: number;
  statuses: { [key: string]: number };
}
