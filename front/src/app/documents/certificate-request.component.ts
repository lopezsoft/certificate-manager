import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {BaseComponent} from "../@core/components/base/base.component";
import {SearchDataComponent} from "../common/components/search-data/search-data.component";
import { ExodoPaginationComponent} from "exodolibs";
import {DocumentViewComponent} from "./document-view/document-view.component";
import { ProcessSoftware, SoftwareTest} from "../models/general-model";
import {FormBuilder, FormGroup} from "@angular/forms";
import {HttpResponsesService, MessagesService} from "../utils";
import TokenService from "../utils/token.service";
import {ActivatedRoute, Router} from "@angular/router";
import {TranslateService} from "@ngx-translate/core";
import {GlobalSettingsService} from "../services/global-settings.service";
import {ShippingService} from "../services/shipping.service";
import {LoadMaskService} from "../services/load-mask.service";
import {FormatsService} from "../services/formats.service";
import {DateManager} from "../common/class/date-manager";
import {CertificateRequest} from "../interfaces/file-manager.interface";
import {DocumentStatusDescription, DocumentStatusEnumArray} from "../common/enums/DocumentStatus";
import {QuotaService} from "../services/quota.service";
import {Subscription} from "rxjs";

@Component({
    selector: 'app-certificate-request',
    templateUrl: './certificate-request.component.html',
    styleUrl: './certificate-request.component.scss',
    standalone: false
})
export class CertificateRequestComponent extends BaseComponent  implements OnInit, AfterViewInit, OnDestroy  {
  @ViewChild('searchItems') searchItems: SearchDataComponent;
  @ViewChild('pagination') pagination: ExodoPaginationComponent;
  @ViewChild('documentView') documentView: DocumentViewComponent;
  protected readonly documentStatusDescription = DocumentStatusDescription;
  interval: any;
  process: ProcessSoftware;
  software: SoftwareTest;
  public modalForm: FormGroup;
  public title = 'Solicitudes de certificado';
  public statusDocument = DocumentStatusEnumArray;
  protected currentTypePersons: any;
  protected isClicked = false;
  protected readonly isAdmin: boolean;
  protected readonly hasAgreement: boolean;

  private quotaSub: Subscription | null = null;

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
      public quotaService: QuotaService,
  ) {
    super(_token, router, translate);
    this.isAdmin = this._token.isAdmin();
    this.hasAgreement = this._token.getToken()?.company?.has_agreement ?? false;
    const currentDate = DateManager.currentDate();
    this.modalForm = this.fb.group({
      start_date: [DateManager.oldDate()],
      end_date: [currentDate],
      request_status: [''],
    });
  }

  ngOnInit(): void {
    // Ya no se consulta cupo aquí — viene del polling global en AppComponent.
    // Suscribirse al observable reactivo para refrescar la vista.
    this.quotaSub = this.quotaService.quotaStatus$.subscribe();
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
    const values  = this.modalForm.getRawValue();
    if ((values.start_date.length > 0 && values.end_date.length > 0)) {
      values.limit  = 20;
    }
    query = {...query, ...values};
    this.mask.showBlockUI('Cargando datos...');
    if (this.shipping.currentShipping) {
      this.shipping.currentShipping.checked = false;
      this.shipping.currentShipping = null;
    }
    this.isClicked = false;
    this.shipping.getShipping(query).subscribe({
      next: () => {
        this.mask.hideBlockUI();
        this.searchItems.searchField.nativeElement.focus();
        if (this.shipping.shippingData.length === 1) {
          setTimeout(() => {
            this.onClickItem(this.shipping.shippingData[0]);
          });
        }
        this.setPagination();
      },
      error: () => {
        this.shipping.shippingData = [];
        this.mask.hideBlockUI();
      }
    });
  }

  protected onClickItem(row: CertificateRequest): void {
    if (this.shipping.currentShipping) {
      this.shipping.currentShipping.checked = false;
    }
    row.checked = true;
    this.shipping.currentShipping = row;
    this.shipping.currentShipping.checked = true;
    this.isClicked = true;
    this.documentView.initData();
  }

  toggleNavbar() {
    this.isClicked = false;
    const currentProduct  = this.shipping.shippingData.find((row) => row.checked);
    currentProduct.checked = false;
    this.shipping.currentShipping = null;
  }

  clearFilter() {
    if (this.currentTypePersons) {
      this.currentTypePersons.selected = false;
      this.currentTypePersons = null;
    }
    this.modalForm.reset();
    this.modalForm.get('start_date').setValue(DateManager.oldDate());
    this.modalForm.get('end_date').setValue(DateManager.currentDate());
  }

  onDeleteRequest(item: CertificateRequest, event: Event) {
    event.stopPropagation();
    this.msg.confirm('¿Eliminar solicitud?', `¿Está seguro de eliminar la solicitud de ${item.company_name}? Esta acción no se puede deshacer.`)
      .then((result) => {
        if (result.isConfirmed) {
          this.mask.showBlockUI('Eliminando...');
          this.api.delete(`/certificate-request/${item.id}`).subscribe({
            next: (resp: any) => {
              this.mask.hideBlockUI();
              this.msg.toastMessage('Éxito', resp.message || 'Solicitud eliminada correctamente');
              this.onSearch(); // Refrescar la lista
            },
            error: () => {
              this.mask.hideBlockUI();
            }
          });
        }
      });
  }

  private setPagination() {
    this.pagination.setPagination(this.shipping.shippingDataRecords);
  }

  ngOnDestroy(): void {
    clearInterval(this.interval);
    this.quotaSub?.unsubscribe();
  }

  protected onNewDocument() {
    if (!this.quotaService.hasQuota) {
      if (this.isAdmin) {
        this.openQuotaModal();
      } else {
        this.msg.toastMessage(
          'Sin cupos disponibles',
          'No tiene certificados disponibles. Será redirigido al módulo de compra.',
          3
        );
        setTimeout(() => {
          this.router.navigate(['/orders/purchase']);
        }, 2000);
      }
      return;
    }
    this.router.navigate(['requests/list/create']);
  }

  /**
   * Emite la señal para que AppComponent abra el modal de asignación de cuota.
   */
  protected openQuotaModal(): void {
    this.quotaService.resetNotification();
    // Forzar re-emisión de la señal
    const today = new Date();
    const nextYear = new Date(today);
    nextYear.setFullYear(nextYear.getFullYear() + 1);
    this.quotaService.emitAdminQuotaSignal({
      company_id: 0,
      pricing_tier_id: 1,
      quantity: 10000,
      period_start: this.quotaService.formatDate(today),
      period_end: this.quotaService.formatDate(nextYear),
      notes: 'Cuota incluida — Administrador del sistema',
    });
  }
}
