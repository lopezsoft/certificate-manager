import { Injectable, OnDestroy } from '@angular/core';
import { BehaviorSubject, Observable, Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { OrderService } from './order.service';
import { OrderResponse, Order } from '../interfaces/order.interface';
import { QuotaService } from './quota.service';
import { MessagesService } from '../utils';
import { DebugService } from '../utils/debug.service';
import { environment } from '../../environments/environment';

/**
 * Estado del modal de pago Wompi.
 */
export interface WompiPaymentState {
  /** Si el modal está visible */
  visible: boolean;
  /** Datos de la orden para el widget */
  orderData: OrderResponse | null;
  /** Si se está cargando datos de reintento */
  loading: boolean;
  /** Si el widget se abrió y se está esperando confirmación */
  polling: boolean;
  /** Estado actual del pago */
  paymentStatus: string;
  /** UUID de la orden en proceso */
  orderUuid: string | null;
}

const INITIAL_STATE: WompiPaymentState = {
  visible: false,
  orderData: null,
  loading: false,
  polling: false,
  paymentStatus: '',
  orderUuid: null,
};

/**
 * WompiPaymentService — Servicio centralizado para gestionar el flujo de pago con Wompi.
 *
 * Permite abrir el modal de pago desde cualquier componente del sistema
 * mediante `openPayment()` (con datos existentes) o `retryPayment()` (reintento).
 *
 * El usuario puede cerrar el modal en CUALQUIER momento:
 *   - Durante carga de datos → cancela la petición
 *   - Después de abrir el widget → detiene el polling
 *   - Durante polling → detiene el polling, la orden permanece PENDING
 */
@Injectable({
  providedIn: 'root'
})
export class WompiPaymentService implements OnDestroy {

  /** Estado reactivo del modal */
  private readonly stateSubject = new BehaviorSubject<WompiPaymentState>({ ...INITIAL_STATE });
  readonly state$ = this.stateSubject.asObservable();

  /** Emite cuando un pago se confirma exitosamente */
  private readonly paymentCompletedSubject = new Subject<Order>();
  readonly paymentCompleted$ = this.paymentCompletedSubject.asObservable();

  /** Detiene operaciones activas (polling, retry request) sin destruir el servicio */
  private stopPolling$ = new Subject<void>();

  /** Destruye el servicio completo */
  private readonly destroy$ = new Subject<void>();

  /** Acceso rápido al estado actual */
  get state(): WompiPaymentState {
    return this.stateSubject.value;
  }

  constructor(
    private orderService: OrderService,
    private quotaService: QuotaService,
    private msg: MessagesService,
    private debug: DebugService,
  ) {}

  /**
   * Abre el modal con datos de pago ya disponibles.
   * Usado desde PurchaseComponent después de crear la orden.
   */
  openPayment(orderData: OrderResponse): void {
    this.cancelActiveOperations();
    this.stateSubject.next({
      ...INITIAL_STATE,
      visible: true,
      orderData,
      orderUuid: orderData.order_id,  // order_id es el UUID
    });
  }

  /**
   * Reintenta el pago de una orden PENDING.
   * Llama al backend para obtener datos frescos y abre el modal.
   */
  retryPayment(uuid: string): void {
    this.cancelActiveOperations();
    this.stateSubject.next({
      ...INITIAL_STATE,
      visible: true,
      loading: true,
      orderUuid: uuid,
    });

    this.orderService.retryOrder(uuid)
      .pipe(takeUntil(this.stopPolling$), takeUntil(this.destroy$))
      .subscribe({
        next: (orderData) => {
          this.stateSubject.next({
            ...this.state,
            loading: false,
            orderData,
          });
        },
        error: (err) => {
          this.debug.error('WompiPaymentService', 'Error al obtener datos de reintento', err);
          this.msg.errorMessage('Error', 'No se pudieron obtener los datos de pago. Intente nuevamente.');
          this.close();
        }
      });
  }

  /**
   * Abre el widget de Wompi con los datos actuales.
   */
  openWidget(): void {
    const { orderData } = this.state;
    if (!orderData) return;

    if (typeof WidgetCheckout === 'undefined') {
      this.debug.error('WompiPaymentService', 'Widget de Wompi no disponible');
      this.msg.errorMessage('Error', 'El servicio de pago no está disponible. Recargue la página e intente nuevamente.');
      return;
    }

    const amountInCents = Math.round(Number(orderData.total_amount) * 100);

    this.debug.log('WompiPaymentService', 'Abriendo widget de Wompi', {
      reference: orderData.provider_reference,
      amountInCents,
    });

    const checkout = new WidgetCheckout({
      currency: orderData.currency || 'COP',
      amountInCents,
      reference: orderData.provider_reference,
      publicKey: environment.WOMPI_PUBLIC_KEY,
      signature: { integrity: orderData.integrity_hash },
      redirectUrl: `${window.location.origin}/#/orders`,
    });

    checkout.open((result: WompiTransactionResult) => {
      this.debug.log('WompiPaymentService', 'Resultado del widget Wompi', result);
      this.startPolling();
    });
  }

  /**
   * Cierra el modal, detiene polling y resetea el estado.
   * Puede llamarse en CUALQUIER momento.
   */
  close(): void {
    this.cancelActiveOperations();
    this.stateSubject.next({ ...INITIAL_STATE });
  }

  /**
   * Cancela operaciones activas (polling/requests) sin cerrar el modal.
   */
  private cancelActiveOperations(): void {
    this.stopPolling$.next();
    this.stopPolling$.complete();
    this.stopPolling$ = new Subject<void>();
  }

  /**
   * Inicia polling del estado de la orden hasta que sea PAID.
   */
  private startPolling(): void {
    const { orderUuid } = this.state;
    if (!orderUuid) return;

    this.stateSubject.next({
      ...this.state,
      polling: true,
      paymentStatus: 'PENDING',
    });

    this.orderService.pollOrderStatus(orderUuid)
      .pipe(
        takeUntil(this.stopPolling$),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (order: Order) => {
          this.stateSubject.next({
            ...this.state,
            paymentStatus: order.status,
          });

          if (order.status === 'PAID') {
            this.stateSubject.next({
              ...this.state,
              polling: false,
              paymentStatus: 'PAID',
            });
            this.msg.toastMessage(
              '¡Pago confirmado!',
              'Su compra ha sido procesada exitosamente. Los certificados ya están disponibles.',
            );
            this.quotaService.getQuotaStatus().subscribe();
            this.paymentCompletedSubject.next(order);
          }
        },
        error: (err) => {
          this.debug.error('WompiPaymentService', 'Error en polling', err);
          this.stateSubject.next({
            ...this.state,
            polling: false,
            paymentStatus: 'ERROR',
          });
          this.msg.errorMessage('Error', 'No se pudo verificar el estado del pago. Consulte sus órdenes.');
        }
      });
  }

  ngOnDestroy(): void {
    this.cancelActiveOperations();
    this.destroy$.next();
    this.destroy$.complete();
  }
}
