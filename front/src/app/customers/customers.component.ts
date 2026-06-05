import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {HttpResponsesService, MessagesService} from "../utils";
import TokenService from "../utils/token.service";
import {ActivatedRoute, Router} from "@angular/router";
import {TranslateService} from "@ngx-translate/core";
import {ExodoPaginationComponent} from "exodolibs";
import {Company} from "../models/companies-model";
import { SearchDataComponent } from 'app/common/components/search-data/search-data.component';
import { CustomerViewComponent } from './customer-view/customer-view.component';
import { DocumentStatusDescription } from 'app/common/enums/DocumentStatus';
import { ProcessSoftware, SoftwareTest } from 'app/models/general-model';
import { FormBuilder } from '@angular/forms';
import { BaseComponent } from 'app/@core/components/base/base.component';
import { CustomerService } from 'app/services/companies/customers.service';
import { FormatsService } from 'app/services/formats.service';
import { LoadMaskService } from 'app/services/load-mask.service';
import { ShippingService } from 'app/services/shipping.service';
import { GlobalSettingsService } from 'app/services/global-settings.service';

@Component({
    selector: 'app-customers',
    templateUrl: './customer-component.html',
    styleUrls: ['./customers.component.scss'],
    standalone: false
})
export class CustomersComponent extends BaseComponent  implements OnInit, AfterViewInit, OnDestroy  {
  @ViewChild('searchItems') searchItems: SearchDataComponent;
  @ViewChild('pagination') pagination: ExodoPaginationComponent;
  @ViewChild('documentView') documentView: CustomerViewComponent;
  protected readonly documentStatusDescription = DocumentStatusDescription;
  interval: any;
  process: ProcessSoftware;
  software: SoftwareTest;
  public title = 'Lista de Clientes';
  protected isClicked = false;

  /** Mostrar clientes desactivados */
  protected showInactive = false;

  constructor(
      public msg: MessagesService,
      public api: HttpResponsesService,
      public _token: TokenService,
      public router: Router,
      public fb: FormBuilder,
      public translate: TranslateService,
      public aRouter: ActivatedRoute,
      public settings: GlobalSettingsService,
      public shipping: ShippingService,
      private mask: LoadMaskService,
      public format: FormatsService,
      public customer: CustomerService,
  ) {
    super(_token, router, translate);
  }

  ngOnInit(): void {
  }

  ngAfterViewInit(): void {
    this.onSearch();
  }

  protected onRefreshPagination($event: number) {
    this.onSearch({
      page: $event,
    });
  }

  protected onSearch(query: any = {}): void {
    this.mask.showBlockUI('Cargando datos...');
    if (this.customer.currentCustomer) {
      this.customer.currentCustomer.checked = false;
      this.customer.currentCustomer = null;
    }
    this.isClicked = false;
    query.limit   = 30;
    query.where   = JSON.stringify({active: this.showInactive ? false : true});
    this.customer.getData(query).subscribe({
      next: () => {
        this.mask.hideBlockUI();
        this.searchItems.searchField.nativeElement.focus();
        if (this.customer.data.length === 1) {
          this.onClickItem(this.customer.data[0]);
        }
        this.setPagination();
      },
      error: () => {
        this.customer.data = [];
        this.mask.hideBlockUI();
      }
    });
  }

  protected onClickItem(row: Company): void {
    if (this.customer.currentCustomer) {
      this.customer.currentCustomer.checked = false;
    }
    row.checked = true;
    this.customer.currentCustomer = row;
    this.customer.currentCustomer.checked = true;
    this.isClicked = true;
    this.documentView.loadStats(row.id);
  }

  toggleNavbar() {
    this.isClicked = false;
    const currentProduct  = this.customer.data.find((row) => row.checked);
    currentProduct.checked = false;
    this.customer.currentCustomer = null;
  }

  /** Toggle del switch: mostrar activos / inactivos */
  protected onToggleInactive(): void {
    this.showInactive = !this.showInactive;
    this.onSearch();
  }

  /** Activar o desactivar una empresa (PATCH toggle-active) */
  protected onToggleActive(company: Company): void {
    const action = company.active ? 'desactivar' : 'activar';
    this.msg.confirm(
      `${action.charAt(0).toUpperCase() + action.slice(1)} empresa`,
      `¿Está seguro de que desea ${action} a <strong>${company.company_name}</strong>?`,
    ).then((result) => {
      if (result.isConfirmed) {
        this.mask.showBlockUI(`${action.charAt(0).toUpperCase() + action.slice(1)}ndo empresa...`);
        this.customer.toggleActive(company.id).subscribe({
          next: (resp: any) => {
            this.mask.hideBlockUI();
            this.msg.toastMessage(
              'Operación exitosa',
              resp.message || `Empresa ${action}da correctamente.`,
              1
            );
            this.onSearch();
          },
          error: () => {
            this.mask.hideBlockUI();
            this.msg.toastMessage('Error', `No se pudo ${action} la empresa.`, 3);
          }
        });
      }
    });
  }

  private setPagination() {
    this.pagination.setPagination(this.customer.dataRecords);
  }

  ngOnDestroy(): void {
    clearInterval(this.interval);
  }
}
