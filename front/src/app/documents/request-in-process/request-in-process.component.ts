import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {BaseComponent} from "../../@core/components/base/base.component";
import {SearchDataComponent} from "../../common/components/search-data/search-data.component";
import {ExodoPaginationComponent} from "exodolibs";
import {DocumentViewComponent} from "../document-view/document-view.component";
import { ProcessSoftware, SoftwareTest} from "../../models/general-model";
import {FormBuilder, FormGroup} from "@angular/forms";
import {HttpResponsesService, MessagesService} from "../../utils";
import TokenService from "../../utils/token.service";
import {ActivatedRoute, Router} from "@angular/router";
import {TranslateService} from "@ngx-translate/core";
import {GlobalSettingsService} from "../../services/global-settings.service";
import {ShippingService} from "../../services/shipping.service";
import {LoadMaskService} from "../../services/load-mask.service";
import {FormatsService} from "../../services/formats.service";
import {DateManager} from "../../common/class/date-manager";
import {CertificateRequest} from "../../interfaces/file-manager.interface";
import {
  DocumentStatusDescription,
  DocumentStatusEnumArray
} from "../../common/enums/DocumentStatus";

@Component({
  selector: 'app-request-in-process',
  templateUrl: './request-in-process.component.html',
  styleUrl: './request-in-process.component.scss'
})
export class RequestInProcessComponent extends BaseComponent  implements OnInit, AfterViewInit, OnDestroy  {
  @ViewChild('searchItems') searchItems: SearchDataComponent;
  @ViewChild('pagination') pagination: ExodoPaginationComponent;
  @ViewChild('documentView') documentView: DocumentViewComponent;
  interval: any;
  process: ProcessSoftware;
  software: SoftwareTest;
  public modalForm: FormGroup;
  public title = 'Solicitudes en proceso';
  protected currentTypePersons: any;
  protected isClicked = false;
  protected readonly statusDocument = DocumentStatusEnumArray;
  protected readonly documentStatusDescription = DocumentStatusDescription;
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
  ) {
    super(_token, router, translate);
    const currentDate = DateManager.currentDate();
    this.modalForm = this.fb.group({
      start_date: [DateManager.oldDate()],
      end_date: [currentDate],
      request_status: [''],
    });
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
    const values  = this.modalForm.getRawValue();
    if ((values.start_date.length > 0 && values.end_date.length > 0)) {
      values.limit  = 20;
    }
    query = {...query, ...values};
    this.mask.showBlockUI('Cargando datos...');
    if (this.shipping.currentRequestAll) {
      this.shipping.currentRequestAll.checked = false;
      this.shipping.currentRequestAll = null;
    }
    this.isClicked = false;
    this.shipping.getAll(query).subscribe({
      next: () => {
        this.mask.hideBlockUI();
        this.searchItems.searchField.nativeElement.focus();
        if (this.shipping.requestDataAll.length === 1) {
          this.onClickItem(this.shipping.requestDataAll[0]);
        }
        this.setPagination();
      },
      error: () => {
        this.shipping.requestDataAll = [];
        this.mask.hideBlockUI();
      }
    });
  }

  protected onClickItem(row: CertificateRequest): void {
    if (this.shipping.currentRequestAll) {
      this.shipping.currentRequestAll.checked = false;
    }
    row.checked = true;
    this.shipping.currentRequestAll = row;
    this.shipping.currentRequestAll.checked = true;
    this.isClicked = true;
    this.documentView.initData();
  }

  toggleNavbar() {
    this.isClicked = false;
    const currentProduct  = this.shipping.requestDataAll.find((row) => row.checked);
    currentProduct.checked = false;
    this.shipping.currentRequestAll = null;
  }

  clearFilter() {
    if (this.currentTypePersons) {
      this.currentTypePersons.selected = false;
      this.currentTypePersons = null;
    }
    this.modalForm.reset();
    this.modalForm.get('document_type').setValue(0);
    this.modalForm.get('document_status').setValue(-1);
    this.modalForm.get('start_date').setValue(DateManager.oldDate());
    this.modalForm.get('end_date').setValue(DateManager.currentDate());
  }
  private setPagination() {
    this.pagination.setPagination(this.shipping.requestDataRecordsAll);
  }

  ngOnDestroy(): void {
    clearInterval(this.interval);
  }


  protected onNewDocument() {
    this.router.navigate(['requests/list/create']);
  }
}
