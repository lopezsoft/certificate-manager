import { AfterViewInit, Component, OnInit, ViewChild } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { Router } from '@angular/router';
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

/**
 * OrderListComponent — Listado de órdenes de compra de certificados.
 *
 * Muestra las órdenes de la empresa con filtro de estado, búsqueda y paginación.
 * Sigue el mismo patrón del CertificateRequestComponent existente.
 */
@Component({
  selector: 'app-order-list',
  templateUrl: './order-list.component.html',
  styleUrl: './order-list.component.scss'
})
export class OrderListComponent extends BaseComponent implements OnInit, AfterViewInit {
  @ViewChild('searchItems') searchItems: SearchDataComponent;
  @ViewChild('pagination') pagination: ExodoPaginationComponent;

  title = 'Órdenes de compra';
  modalForm: FormGroup;
  protected readonly orderStatusDescription = OrderStatusDescription;

  constructor(
    public msg: MessagesService,
    public orderService: OrderService,
    public format: FormatsService,
    public _token: TokenService,
    public router: Router,
    public translate: TranslateService,
    private fb: FormBuilder,
    private mask: LoadMaskService,
  ) {
    super(_token, router, translate);
    this.modalForm = this.fb.group({
      status: [''],
    });
  }

  ngOnInit(): void {}

  ngAfterViewInit(): void {
    this.onSearch();
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

  protected formatCurrency(value: number, currency: string = 'COP'): string {
    return this.format.getCurrencyFormat('es-CO', currency, value);
  }

  private setPagination(): void {
    if (this.pagination && this.orderService.orderDataRecords) {
      this.pagination.setPagination(this.orderService.orderDataRecords);
    }
  }
}
