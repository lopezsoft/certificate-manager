import { AfterViewInit, Component, OnDestroy, OnInit, ViewChild } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { TranslateService } from '@ngx-translate/core';
import { ExodoPaginationComponent } from 'exodolibs';
import { SearchDataComponent } from '../../common/components/search-data/search-data.component';
import { BaseComponent } from '../../@core/components/base/base.component';
import { OrderService } from '../../services/order.service';
import { Order } from '../../interfaces/order.interface';
import { OrderStatusDescription } from '../../common/enums/OrderStatus';
import { FormatsService } from '../../services/formats.service';
import { LoadMaskService } from '../../services/load-mask.service';
import { MessagesService } from '../../utils';
import TokenService from '../../utils/token.service';
import { WompiPaymentService } from '../../services/wompi-payment.service';

/**
 * OrderListComponent — Listado de órdenes de compra de certificados.
 *
 * Muestra las órdenes de la empresa con filtro de estado, búsqueda y paginación.
 * Incluye botón de reintento de pago para órdenes PENDING.
 */
@Component({
    selector: 'app-order-list',
    templateUrl: './order-list.component.html',
    styleUrl: './order-list.component.scss',
    standalone: false
})
export class OrderListComponent extends BaseComponent implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('searchItems') searchItems: SearchDataComponent;
  @ViewChild('pagination') pagination: ExodoPaginationComponent;

  title = 'Órdenes de compra';
  modalForm: FormGroup;
  protected readonly orderStatusDescription = OrderStatusDescription;

  private readonly destroy$ = new Subject<void>();

  constructor(
    public msg: MessagesService,
    public orderService: OrderService,
    public format: FormatsService,
    public _token: TokenService,
    public router: Router,
    public translate: TranslateService,
    private fb: FormBuilder,
    private mask: LoadMaskService,
    private wompiPaymentService: WompiPaymentService,
  ) {
    super(_token, router, translate);
    this.modalForm = this.fb.group({
      status: [''],
    });
  }

  ngOnInit(): void {
    // Refrescar la tabla cuando un pago se confirme
    this.wompiPaymentService.paymentCompleted$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.onSearch();
      });
  }

  ngAfterViewInit(): void {
    this.onSearch();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  protected onSearch(query: any = {}): void {
    const values = this.modalForm.getRawValue();
    query = { ...query, ...values, limit: 20 };
    this.mask.showBlockUI('Cargando órdenes...');
    this.orderService.getOrders(query).subscribe({
      next: () => {
        this.mask.hideBlockUI();
        if (this.searchItems) {
          this.searchItems.searchField.nativeElement.focus();
        }
        this.setPagination();
      },
      error: () => {
        this.orderService.orders = [];
        this.mask.hideBlockUI();
      }
    });
  }

  protected onRefreshPagination($event: number): void {
    this.onSearch({ page: $event });
  }

  protected onNewOrder(): void {
    this.router.navigate(['/orders/purchase']);
  }

  /**
   * Reintenta el pago de una orden PENDING.
   * Abre el modal global de Wompi con los datos de la orden.
   */
  protected onRetryPayment(order: Order): void {
    this.wompiPaymentService.retryPayment(order.uuid);
  }

  /**
   * Cancela/elimina una orden PENDING con confirmación.
   */
  protected onCancelOrder(order: Order): void {
    this.msg.confirm(
      '¿Cancelar orden?',
      `Se eliminará la orden ${order.provider_reference}. Esta acción no se puede deshacer.`,
    ).then((result: any) => {
      if (result.isConfirmed) {
        this.mask.showBlockUI('Cancelando orden...');
        this.orderService.cancelOrder(order.uuid).subscribe({
          next: () => {
            this.mask.hideBlockUI();
            this.msg.toastMessage('Orden cancelada', `La orden ${order.provider_reference} ha sido eliminada.`);
            this.setPagination();
          },
          error: () => {
            this.mask.hideBlockUI();
            this.msg.errorMessage('Error', 'No se pudo cancelar la orden. Intente nuevamente.');
          }
        });
      }
    });
  }

  protected formatCurrency(value: number, currency: string = 'COP'): string {
    return this.format.getCurrencyFormat('es-CO', currency, value);
  }

  private setPagination(): void {
    if (this.pagination && this.orderService.orderDataRecords) {
      this.pagination.setPagination(this.orderService.orderDataRecords);
    }
  }
}
