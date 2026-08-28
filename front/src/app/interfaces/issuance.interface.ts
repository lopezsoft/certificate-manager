import { ViafirmaInternalStateEnum } from '../common/enums/ViafirmaInternalState';

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
  created_at?: string;
  updated_at?: string;
  /** Código de la solicitud asignado por el proveedor (ej. Viafirma) */
  external_id?: string;
  data?: ViafirmaStatus | any;
}

export interface IssuanceDownloadMeta {
  pin: string;
  download_url: string;
  expires_at: string;
  file_name: string;
}

export interface Base64DownloadResponse {
  p12_pin: string;
  p12_filename: string;
  p12_base64: string;
}

/** Estado detallado del trámite Viafirma */
export interface ViafirmaStatus {
  internal_state: ViafirmaInternalStateEnum;
  remote_status: string;
  public_id: string | null;
  cod_request: string | null;
  submitted_at: string | null;
  assembled_at: string | null;
  expires_at: string | null;
  revocation_code: string | null;
  revoked_at: string | null;
  poll_attempts: number;
  last_error_code: string | null;
  last_error_message: string | null;
  /** Link de acreditación KYC (MetaMap), disponible durante la familia de estados de acreditación */
  kyc_accreditation_link?: string | null;
  /** Marca de tiempo: cuándo el cliente completó la verificación de identidad en MetaMap */
  kyc_flow_completed_at?: string | null;
  history_count?: number;
}

/** Resultado de la re-descarga (solo admin) */
export interface RedownloadResult {
  pin: string;
  download_url: string;
  expires_at: string | null;
  viafirma_id: number;
  internal_state: ViafirmaInternalStateEnum;
  remote_status: string;
}
