import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import { DebugService } from '../utils/debug.service';
import { ExpiringCertificate, ExpiringByCompany } from '../interfaces';

/**
 * CertificateNotificationService — Gestiona consultas de certificados próximos a vencer.
 *
 * Endpoint: GET /certificates/expiring?days=N
 *   - Admin: ve certificados de TODAS las empresas
 *   - Otros: solo los de su empresa
 */
@Injectable({
  providedIn: 'root'
})
export class CertificateNotificationService {

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) {}

  /**
   * Obtiene certificados próximos a vencer.
   * @param days Días de antelación (default: 30). Negativo para vencidos.
   */
  getExpiring(days: number = 30): Observable<{ total: number; data: ExpiringCertificate[] }> {
    this.debug.log('CertificateNotificationService', `Consultando certificados (${days} días)`);
    return this.http.get('/certificates/expiring', { days }).pipe(
      map((resp: any) => {
        const result = {
          total: resp.total ?? resp.dataRecords?.length ?? 0,
          data: resp.dataRecords ?? [],
        };
        this.debug.log('CertificateNotificationService', `${result.total} certificado(s) encontrados`, result);
        return result;
      }),
    );
  }

  /**
   * Vista agrupada por empresa — solo admin.
   * @param days Días de antelación (positivo: por vencer, negativo: vencidos).
   */
  getExpiringByCompany(days: number = 30): Observable<{ total: number; data: ExpiringByCompany[] }> {
    this.debug.log('CertificateNotificationService', `Consultando agrupado por empresa (${days} días)`);
    return this.http.get('/admin/certificates/expiring-by-company', { days }).pipe(
      map((resp: any) => {
        const result = {
          total: resp.total ?? resp.dataRecords?.length ?? 0,
          data: resp.dataRecords ?? [],
        };
        this.debug.log('CertificateNotificationService', `${result.total} empresa(s) encontradas`, result);
        return result;
      }),
    );
  }
}
