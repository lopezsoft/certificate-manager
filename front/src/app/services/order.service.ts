import { Injectable, OnDestroy } from '@angular/core';
import { Observable, Subject, interval } from 'rxjs';
import { map, switchMap, takeUntil, takeWhile, tap } from 'rxjs/operators';
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
 *   POST /orders              → Crear orden
 *   POST /orders/{id}/pay     → Ejecutar pago
 *   GET  /orders/{id}         → Detalle de orden (polling)
 *   GET  /orders              → Listado de órdenes
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
  payOrder(orderId: number, payment: PaymentRequest): Observable<PaymentResponse> {
    return this.http.post(`/orders/${orderId}/pay`, payment).pipe(
      map((res: any) => {
        const tx = res.data as PaymentResponse;
        this.debug.log('OrderService', 'Pago ejecutado', tx);
        return tx;
      }),
    );
  }

  /**
   * Obtiene el detalle de una orden.
   */
  getOrder(orderId: number): Observable<Order> {
    return this.http.get(`/orders/${orderId}`).pipe(
      map((res: any) => res.dataRecords?.data?.[0] ?? res.data as Order),
    );
  }

  /**
   * Lista las órdenes de la empresa del usuario (paginado).
   */
  getOrders(params: any = {}): Observable<Order[]> {
    return this.http.get('/orders', params).pipe(
      map((res: any) => {
        this.orders = res.dataRecords.data;
        this.orderDataRecords = res.dataRecords;
        return this.orders;
      }),
    );
  }

  /**
   * Hace polling al detalle de una orden cada 5 segundos
   * hasta que el status cambie a PAID (o se destruya el servicio).
   *
   * @param orderId  ID de la orden a monitorear.
   * @returns Observable que emite cada estado intermedio y completa cuando es PAID.
   */
  pollOrderStatus(orderId: number): Observable<Order> {
    return interval(5000).pipe(
      takeUntil(this.destroy$),
      switchMap(() => this.getOrder(orderId)),
      tap((order) => {
        this.debug.log('OrderService', `Polling orden #${orderId}`, order.status);
      }),
      takeWhile((order) => order.status !== 'PAID', true),
    );
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
