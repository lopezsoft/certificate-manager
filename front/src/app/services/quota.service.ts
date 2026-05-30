import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { map, tap } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import { QuotaStatus } from '../interfaces/quota.interface';
import { DebugService } from '../utils/debug.service';

/**
 * QuotaService — Gestión centralizada del estado de cupos de certificados.
 *
 * Expone un BehaviorSubject reactivo para que múltiples componentes
 * (dashboard, certificate-request, create-request) reaccionen al estado de cupo
 * sin duplicar llamadas HTTP.
 *
 * Endpoints consumidos:
 *   GET /quota/status
 */
@Injectable({
  providedIn: 'root'
})
export class QuotaService {

  private readonly quotaSubject = new BehaviorSubject<QuotaStatus | null>(null);

  /** Observable del estado de cupo actual */
  readonly quotaStatus$: Observable<QuotaStatus | null> = this.quotaSubject.asObservable();

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) {}

  /**
   * Consulta el estado de cupo al backend y actualiza el BehaviorSubject.
   * @returns Observable<QuotaStatus> con los datos frescos.
   */
  getQuotaStatus(): Observable<QuotaStatus> {
    return this.http.get('/quota/status').pipe(
      map((res: any) => res.dataRecords as QuotaStatus),
      tap((quota) => {
        this.quotaSubject.next(quota);
        this.debug.log('QuotaService', 'Estado de cupo actualizado', quota);
      }),
    );
  }

  /** Getter sincrónico: ¿el usuario tiene cupo disponible? */
  get hasQuota(): boolean {
    return this.quotaSubject.value?.has_quota ?? false;
  }

  /**
   * Total de cupos disponibles = prepaid + postpaid.remaining.
   */
  get totalAvailable(): number {
    const q = this.quotaSubject.value;
    if (!q) return 0;
    return q.prepaid_items_available + (q.postpaid?.remaining ?? 0);
  }

  /** Valor actual del subject (snapshot). */
  get currentQuota(): QuotaStatus | null {
    return this.quotaSubject.value;
  }
}
