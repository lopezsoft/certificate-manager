import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import {
  IssuanceRequest,
  IssuanceStatus,
  IssuanceDownloadMeta,
  RedownloadResult,
  Base64DownloadResponse,
} from '../interfaces/issuance.interface';
import { DebugService } from '../utils/debug.service';

/**
 * IssuanceService — Emisión, estado y descarga de certificados.
 *
 * Endpoints consumidos:
 *   POST /certificate-request/{id}/issue               → Disparar emisión
 *   GET  /certificate-request/{id}/issuance             → Estado del trámite
 *   GET  /certificate-request/{id}/issuance/download    → Metadata de descarga (PIN + URL)
 *   GET  /certificate-request/{id}/issuance/download/base64 → Descarga directa del P12 (base64)
 *   POST /certificate-request/{id}/issuance/redownload  → Re-descarga de certificado (admin)
 *   POST /certificate-request/{id}/revoke               → Revocación de certificado (admin)
 */
@Injectable({
  providedIn: 'root'
})
export class IssuanceService {

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) { }

  /**
   * Dispara la emisión de un certificado.
   * El backend puede responder 200 (emisión por correo) o 201 (solicitud Viafirma creada).
   */
  issue(requestId: number, body?: IssuanceRequest): Observable<any> {
    return this.http.post(`/certificate-request/${requestId}/issue`, body ?? {}).pipe(
      map((res: any) => {
        this.debug.log('IssuanceService', `Emisión disparada para solicitud #${requestId}`, res);
        return res;
      }),
    );
  }

  /**
   * Consulta el estado normalizado del trámite de emisión.
   * Si el proveedor es Viafirma, incluye el metadata detallado en la propiedad "data".
   */
  getIssuanceStatus(requestId: number): Observable<IssuanceStatus> {
    return this.http.get(`/certificate-request/${requestId}/issuance`).pipe(
      map((res: any) => {
        const record = res.dataRecords;
        // Transformar estado de viafirma a mayúsculas si existe
        if (record?.data?.internal_state) {
          record.data.internal_state = record.data.internal_state.toUpperCase();
        }
        this.debug.log('IssuanceService', `Estado emisión solicitud #${requestId}`, record);
        return record as IssuanceStatus;
      }),
    );
  }

  /**
   * Obtiene la metadata de descarga (PIN temporal + URL firmada).
   * Solo aplica para certificados emitidos por Viafirma.
   */
  getDownloadFileUrl(requestUuid: string): Observable<IssuanceDownloadMeta> {
    return this.http.get(`/certificate-request/${requestUuid}/issuance/download`).pipe(
      map((res: any) => {
        const meta = res.dataRecords.data as IssuanceDownloadMeta;
        this.debug.log('IssuanceService', `Metadata descarga solicitud #${requestUuid}`, meta);
        return meta;
      }),
    );
  }

  /**
   * Obtiene el archivo P12 binario para descarga directa, en base64 o streaming.
   * El backend responde con un streaming binario, no JSON.
   */

  getDownloadBase64(requestUuid: string): Observable<Base64DownloadResponse> {
    return this.http.get(`/certificate-request/${requestUuid}/issuance/download/base64`).pipe(
      map((res: any) => {
        return res.dataRecords.data as Base64DownloadResponse;
      }),
    );
  }

  /**
   * Re-descarga el certificado P7B desde Viafirma y regenera el P12 con un nuevo PIN.
   * Solo para administradores. Aplica cuando internal_state es assembled, completed o downloaded.
   */
  redownloadCertificate(requestId: number): Observable<RedownloadResult> {
    return this.http.post(`/certificate-request/${requestId}/issuance/redownload`, {}).pipe(
      map((res: any) => {
        const result = res.dataRecords as RedownloadResult;
        if (result && result.internal_state) {
          result.internal_state = result.internal_state.toUpperCase() as any;
        }
        this.debug.log('IssuanceService', `Re-descarga completada solicitud #${requestId}`, result);
        return result;
      }),
    );
  }

  /**
   * Revoca un certificado emitido en Viafirma.
   */
  revokeCertificate(requestUuid: string, revocation_code: string, revocation_reason: number): Observable<any> {
    return this.http.post(`/certificate-request/${requestUuid}/revoke`, {
      revocation_code,
      revocation_reason
    }).pipe(
      map((res: any) => {
        this.debug.log('IssuanceService', `Revocación exitosa solicitud #${requestUuid}`, res);
        return res;
      }),
    );
  }
}
