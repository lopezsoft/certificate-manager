import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import {
  IssuanceRequest,
  IssuanceStatus,
  IssuanceDownloadMeta,
} from '../interfaces/issuance.interface';
import { DebugService } from '../utils/debug.service';
import { environment } from '../../environments/environment';

/**
 * IssuanceService — Emisión, estado y descarga de certificados.
 *
 * Endpoints consumidos:
 *   POST /certificate-request/{id}/issue               → Disparar emisión
 *   GET  /certificate-request/{id}/issuance             → Estado del trámite
 *   GET  /certificate-request/{id}/issuance/download    → Metadata de descarga (PIN + URL)
 *   GET  /certificate-request/{id}/issuance/download/file → Streaming binario P12
 */
@Injectable({
  providedIn: 'root'
})
export class IssuanceService {

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) {}

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
   */
  getIssuanceStatus(requestId: number): Observable<IssuanceStatus> {
    return this.http.get(`/certificate-request/${requestId}/issuance`).pipe(
      map((res: any) => {
        const status = res.dataRecords as IssuanceStatus;
        this.debug.log('IssuanceService', `Estado emisión solicitud #${requestId}`, status);
        return status;
      }),
    );
  }

  /**
   * Obtiene la metadata de descarga (PIN temporal + URL firmada).
   * Solo aplica para certificados emitidos por Viafirma.
   */
  getDownloadMeta(requestId: number): Observable<IssuanceDownloadMeta> {
    return this.http.get(`/certificate-request/${requestId}/issuance/download`).pipe(
      map((res: any) => {
        const meta = res.dataRecords as IssuanceDownloadMeta;
        this.debug.log('IssuanceService', `Metadata descarga solicitud #${requestId}`, meta);
        return meta;
      }),
    );
  }

  /**
   * Retorna la URL completa para descarga directa del archivo P12.
   * El componente puede usarla con window.open() o como href.
   */
  getDownloadFileUrl(requestId: number): string {
    return `${environment.APIURL}/certificate-request/${requestId}/issuance/download/file`;
  }
}
