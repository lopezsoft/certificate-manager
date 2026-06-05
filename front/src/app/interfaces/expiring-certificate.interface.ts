/**
 * Interfaz para certificados próximos a vencer.
 * Endpoint: GET /api/v1/certificates/expiring?days=N
 */

export type UrgencyLevel = 'critical' | 'high' | 'medium' | 'low';

export interface ExpiringCertificate {
  id: number;
  company_name: string;
  dni: string;
  dv: string;
  email: string;
  phone: string;
  expiration_date: string;
  expiration_date_formatted: string;
  days_remaining: number;
  urgency_level: UrgencyLevel;
  city: string;
  legal_representative: string;
}

export interface ExpiringCertificatesResponse {
  success: boolean;
  message: string;
  total: number;
  dataRecords: ExpiringCertificate[];
}
