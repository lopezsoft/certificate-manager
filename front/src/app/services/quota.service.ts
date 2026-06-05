import { Injectable, OnDestroy } from '@angular/core';
import { BehaviorSubject, Observable, Subject, Subscription, timer } from 'rxjs';
import { map, switchMap, tap } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import { QuotaStatus } from '../interfaces/quota.interface';
import { DebugService } from '../utils/debug.service';
import TokenService from '../utils/token.service';
import Swal from 'sweetalert2';

/**
 * Payload pre-llenado para el modal de asignación de cuota admin.
 */
export interface AdminQuotaPayload {
  company_id: number;
  pricing_tier_id: number;
  quantity: number;
  period_start: string;
  period_end: string;
  notes: string;
}

/**
 * QuotaService — Gestión centralizada del estado de cupos de certificados.
 *
 * Flujo diferenciado por rol:
 *   - Admin (type_id=1): emite señal para abrir modal de asignación de cuota
 *   - Otros roles: muestra SweetAlert con opción de comprar
 *
 * Endpoints consumidos:
 *   GET  /quota/status
 *   POST /admin/quotas
 */
@Injectable({
  providedIn: 'root'
})
export class QuotaService implements OnDestroy {

  private readonly quotaSubject = new BehaviorSubject<QuotaStatus | null>(null);
  private readonly adminQuotaNeededSubject = new Subject<AdminQuotaPayload>();

  /** Observable del estado de cupo actual */
  readonly quotaStatus$: Observable<QuotaStatus | null> = this.quotaSubject.asObservable();

  /**
   * Emite cuando el admin necesita asignar cuota.
   * El AppComponent escucha esto para abrir el modal pre-llenado.
   */
  readonly adminQuotaNeeded$: Observable<AdminQuotaPayload> = this.adminQuotaNeededSubject.asObservable();

  /** Flag para evitar ejecutar la acción de "sin cupo" repetidamente */
  private hasHandledNoQuota = false;

  /** Subscription del polling periódico */
  private pollingSub: Subscription | null = null;

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
    private tokenService: TokenService,
  ) {}

  /**
   * Consulta el estado de cupo al backend y actualiza el BehaviorSubject.
   */
  getQuotaStatus(): Observable<QuotaStatus> {
    return this.http.get('/quota/status').pipe(
      map((res: any) => res.dataRecords as QuotaStatus),
      tap((quota) => {
        this.quotaSubject.next(quota);
        this.debug.log('QuotaService', 'Estado de cupo actualizado', quota);
        this.handleNoQuota(quota);
      }),
    );
  }

  /**
   * Inicia polling periódico del estado de cupo.
   * Primera consulta inmediata, luego cada `intervalMs`.
   */
  startPolling(intervalMs: number = 120_000): void {
    this.stopPolling();
    this.debug.log('QuotaService', `Polling iniciado cada ${intervalMs / 1000}s`);

    this.pollingSub = timer(0, intervalMs).pipe(
      switchMap(() => this.getQuotaStatus()),
    ).subscribe({
      error: (err) => {
        this.debug.error('QuotaService', 'Error en polling de cupo', err);
      },
    });
  }

  /** Detiene el polling periódico. */
  stopPolling(): void {
    if (this.pollingSub) {
      this.pollingSub.unsubscribe();
      this.pollingSub = null;
      this.debug.log('QuotaService', 'Polling detenido');
    }
  }

  /** Resetea el flag de notificación. */
  resetNotification(): void {
    this.hasHandledNoQuota = false;
  }

  /** Getter sincrónico: ¿el usuario tiene cupo disponible? */
  get hasQuota(): boolean {
    return this.quotaSubject.value?.has_quota ?? false;
  }

  /** Total de cupos disponibles = prepaid + postpaid.remaining. */
  get totalAvailable(): number {
    const q = this.quotaSubject.value;
    if (!q) return 0;
    return q.prepaid_items_available + (q.postpaid?.remaining ?? 0);
  }

  /** Valor actual del subject (snapshot). */
  get currentQuota(): QuotaStatus | null {
    return this.quotaSubject.value;
  }

  /**
   * Envía la asignación de cuota del admin al backend.
   * Invocado desde AppComponent tras confirmar el modal.
   */
  assignAdminQuota(payload: AdminQuotaPayload): Observable<any> {
    this.debug.log('QuotaService', 'Asignando cuota de administrador', payload);
    return this.http.post('/admin/quotas', payload);
  }

  ngOnDestroy(): void {
    this.stopPolling();
  }

  // ─── Private ─────────────────────────────────────────────────

  /**
   * Maneja el escenario "sin cupo" según el rol del usuario.
   * - Admin → emite señal para abrir modal de confirmación.
   * - Otros → muestra SweetAlert con opción de comprar.
   */
  private handleNoQuota(quota: QuotaStatus): void {
    if (quota.has_quota) {
      this.hasHandledNoQuota = false;
      return;
    }

    if (this.hasHandledNoQuota) {
      return;
    }

    this.hasHandledNoQuota = true;

    if (this.tokenService.isAdmin()) {
      this.emitAdminQuotaNeeded();
    } else {
      this.notifyNoQuota();
    }
  }

  /**
   * Emite payload pre-llenado por defecto para abrir modal (uso interno del polling).
   */
  private emitAdminQuotaNeeded(): void {
    const today = new Date();
    const nextYear = new Date(today);
    nextYear.setFullYear(nextYear.getFullYear() + 1);

    this.emitAdminQuotaSignal({
      company_id: 0,
      pricing_tier_id: 1,
      quantity: 10000,
      period_start: this.formatDate(today),
      period_end: this.formatDate(nextYear),
      notes: 'Cuota incluida — Administrador del sistema',
    });
  }

  /**
   * Emite señal para que AppComponent abra el modal de asignación de cuota.
   * Puede ser invocado externamente por componentes (ej. CertificateRequestComponent).
   */
  emitAdminQuotaSignal(payload: AdminQuotaPayload): void {
    this.debug.log('QuotaService', 'Admin requiere asignar cuota — abriendo modal', payload);
    this.adminQuotaNeededSubject.next(payload);
  }

  /**
   * SweetAlert para usuarios no-admin.
   * POSPAGO → mensaje de contacto (no puede comprar)
   * PREPAGO → botón de compra
   */
  private notifyNoQuota(): void {
    const token = this.tokenService.getToken();
    const hasAgreement = token?.company?.has_agreement ?? false;

    if (hasAgreement) {
      Swal.fire({
        title: 'Sin cupos disponibles',
        html: 'Su empresa tiene convenio <strong>POSPAGO</strong>.<br>Contacte al administrador para la asignación de cuota.',
        icon: 'info',
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#2556a3',
      });
    } else {
      Swal.fire({
        title: 'Sin cupos disponibles',
        html: 'No tiene certificados disponibles para generar solicitudes.<br>Adquiera un nuevo paquete para continuar.',
        icon: 'warning',
        confirmButtonText: 'Comprar certificados',
        showCancelButton: true,
        cancelButtonText: 'Cerrar',
        confirmButtonColor: '#2556a3',
        cancelButtonColor: '#82868b',
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.hash = '#/orders/purchase';
        }
      });
    }
  }

  /** Formatea una fecha a YYYY-MM-DD. */
  formatDate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
}
