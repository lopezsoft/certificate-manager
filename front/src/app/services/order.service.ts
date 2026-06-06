import { Injectable, OnDestroy } from '@angular/core';
import { Observable, Subject, interval } from 'rxjs';
import { filter, map, switchMap, takeUntil, takeWhile, tap } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import {
  Order,
  OrderCreateRequest,
  OrderResponse,
  PaymentRequest,
  PaymentResponse,
} from '../interfaces/order.interface';
import { DataRecords } from '../interfaces/json-response.interface';
import { DebugService } from '../utils/debug.service';

/**
 * OrderService — Gestión de órdenes de compra y pagos WOMPI.
 *
 * Endpoints consumidos:
 *   POST   /orders              → Crear orden
 *   POST   /orders/{uuid}/pay   → Ejecutar pago
 *   POST   /orders/{uuid}/retry → Reintentar pago
 *   GET    /orders/{uuid}       → Detalle de orden (polling)
 *   GET    /orders              → Listado de órdenes
 *   DELETE /orders/{uuid}       → Cancelar orden
 */
@Injectable({
  providedIn: 'root'
})
export class OrderService implements OnDestroy {

  /** Datos del listado de órdenes */
  orders: Order[] = [];
  orderDataRecords: DataRecords;

  private readonly destroy$ = new Subject<void>();

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) {}

  /**
   * Crea una nueva orden de compra.
   * POST /orders → { data: { order_id (UUID), total_amount, ... } }
   */
  createOrder(request: OrderCreateRequest): Observable<OrderResponse> {
    return this.http.post('/orders', request).pipe(
      map((res: any) => {
        const order = res.data as OrderResponse;
        this.debug.log('OrderService', 'Orden creada', order);
        return order;
      }),
    );
  }

  /**
   * Ejecuta el pago de una orden existente.
   */
  payOrder(uuid: string, payment: PaymentRequest): Observable<PaymentResponse> {
    return this.http.post(`/orders/${uuid}/pay`, payment).pipe(
      map((res: any) => {
        const tx = res.data as PaymentResponse;
        this.debug.log('OrderService', 'Pago ejecutado', tx);
        return tx;
      }),
    );
  }

  /**
   * Obtiene el detalle de una orden por UUID.
   * GET /orders/{uuid} → { data: { uuid, status, ... } }
   *
   * Usa el mismo patrón de extracción que createOrder (res.data).
   */
  getOrder(uuid: string): Observable<Order> {
    return this.http.get(`/orders/${uuid}`).pipe(
      map((res: any) => {
        const order = res.dataRecords.data;
        this.debug.log('OrderService', `getOrder ${uuid}`, order.status);
        return order;
      }),
    );
  }

  /**
   * Lista las órdenes de la empresa del usuario (paginado).
   * GET /orders → { dataRecords: { data: [...], ... } }
   */
  getOrders(params: any = {}): Observable<Order[]> {
    return this.http.get('/orders', params).pipe(
      map((res) => {
        this.orders = res.dataRecords.data;
        this.orderDataRecords = res.dataRecords;
        return this.orders;
      }),
    );
  }

  /**
   * Cancela/elimina una orden PENDING.
   * DELETE /orders/{uuid}
   */
  cancelOrder(uuid: string): Observable<void> {
    return this.http.delete(`/orders/${uuid}`).pipe(
      map(() => {
        this.debug.log('OrderService', `Orden ${uuid} cancelada`);
        this.orders = this.orders.filter(o => o.uuid !== uuid);
      }),
    );
  }

  /**
   * Reintenta el pago de una orden PENDING.
   * POST /orders/{uuid}/retry → { data: { order_id (UUID), acceptance_token, ... } }
   */
  retryOrder(uuid: string): Observable<OrderResponse> {
    return this.http.post(`/orders/${uuid}/retry`, {}).pipe(
      map((res: any) => {
        const order = res.data as OrderResponse;
        this.debug.log('OrderService', 'Datos de reintento obtenidos', order);
        return order;
      }),
    );
  }

  /**
   * Hace polling al detalle de una orden cada 5 segundos
   * hasta que el status cambie a PAID (o se destruya el servicio).
   *
   * @param uuid  UUID de la orden a monitorear.
   * @returns Observable que emite cada estado intermedio y completa cuando es PAID.
   */
  pollOrderStatus(uuid: string): Observable<Order> {
    return interval(5000).pipe(
      takeUntil(this.destroy$),
      switchMap(() => this.getOrder(uuid)),
      filter((order): order is Order => {
        if (!order || !order.status) {
          this.debug.error('OrderService', `Polling orden ${uuid}: respuesta inválida`, order);
          return false;
        }
        return true;
      }),
      tap((order) => {
        this.debug.log('OrderService', `Polling orden ${uuid}`, order.status);
      }),
      takeWhile((order) => order.status !== 'PAID', true),
    );
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
