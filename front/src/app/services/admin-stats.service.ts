import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import { DebugService } from '../utils/debug.service';
import { PaymentsByMonth, UnusedQuotas } from '../interfaces';

/**
 * AdminStatsService — Estadísticas transversales (solo admin).
 *
 * Endpoints:
 *   GET /admin/stats/payments-by-month?year=&company_id=
 *   GET /admin/stats/unused-quotas?company_id=&include_expired=
 */
@Injectable({
  providedIn: 'root'
})
export class AdminStatsService {

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) {}

  /**
   * Pagos por mes de cada cliente (órdenes PAID) para un año.
   */
  getPaymentsByMonth(year: number, companyId?: number): Observable<PaymentsByMonth> {
    const params: any = { year };
    if (companyId) { params.company_id = companyId; }
    this.debug.log('AdminStatsService', `Consultando pagos por mes (${year})`, params);
    return this.http.get('/admin/stats/payments-by-month', params).pipe(
      map((resp: any) => resp.dataRecords as PaymentsByMonth),
    );
  }

  /**
   * Cupos no consumidos por empresa (POSTPAID con saldo + PREPAID pendientes).
   */
  getUnusedQuotas(includeExpired = false, companyId?: number): Observable<UnusedQuotas> {
    const params: any = { include_expired: includeExpired ? 1 : 0 };
    if (companyId) { params.company_id = companyId; }
    this.debug.log('AdminStatsService', 'Consultando cupos no consumidos', params);
    return this.http.get('/admin/stats/unused-quotas', params).pipe(
      map((resp: any) => resp.dataRecords as UnusedQuotas),
    );
  }
}
