/**
 * Interfaces para los endpoints de emisión de certificados
 *
 * POST /certificate-request/{id}/issue              → IssuanceRequest
 * GET  /certificate-request/{id}/issuance            → IssuanceStatus
 * GET  /certificate-request/{id}/issuance/download   → IssuanceDownloadMeta
 * GET  /certificate-request/{id}/issuance/download/file → Streaming binario (no necesita interface)
 */

export interface IssuanceRequest {
  email_certificate?: string;
  comments?: string;
}

export interface IssuanceStatus {
  status: string;
  provider: string;
  created_at: string;
  updated_at: string;
}

export interface IssuanceDownloadMeta {
  pin: string;
  download_url: string;
  expires_at: string;
}
