/**
 * Interfaces para los endpoints de emisión de certificados
 *
 * POST /certificate-request/{id}/issue              → IssuanceRequest
 * GET  /certificate-request/{id}/issuance            → IssuanceStatus
 * GET  /certificate-request/{id}/issuance/download   → IssuanceDownloadMeta
 * GET  /certificate-request/{id}/issuance/download/file → Streaming binario (no necesita interface)
 * POST /certificate-request/{id}/issuance/redownload → RedownloadResult (solo admin)
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

/** Estados internos del trámite Viafirma */
export type ViafirmaInternalState =
  | 'draft'
  | 'submitted'
  | 'polling'
  | 'ready_to_download'
  | 'downloaded'
  | 'assembled'
  | 'completed'
  | 'failed'
  | 'failed_recoverable'
  | 'expired';

/** Estado detallado del trámite Viafirma */
export interface ViafirmaStatus {
  internal_state: ViafirmaInternalState;
  remote_status: string;
  public_id: string | null;
  cod_request: string | null;
  submitted_at: string | null;
  assembled_at: string | null;
  expires_at: string | null;
  poll_attempts: number;
  last_error_code: string | null;
  last_error_message: string | null;
}

/** Resultado de la re-descarga (solo admin) */
export interface RedownloadResult {
  pin: string;
  download_url: string;
  expires_at: string | null;
  viafirma_id: number;
  internal_state: ViafirmaInternalState;
  remote_status: string;
}
