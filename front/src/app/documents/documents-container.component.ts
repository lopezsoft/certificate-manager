import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';

import TokenService from '../utils/token.service';
import {HttpResponsesService, MessagesService} from "../utils";
import {GlobalSettingsService} from "../services/global-settings.service";
import {DianResponse, ProcessSoftware, SoftwareTest} from "../models/general-model";
import {DownloadFile} from "../common/class/download-file";
import {BaseComponent} from "../@core/components/base/base.component";
import {SearchDataComponent} from "../common/components/search-data/search-data.component";
import {ExodoPaginationComponent} from "exodolibs";
import {DateManager} from "../common/class/date-manager";
import {FormBuilder, FormGroup} from "@angular/forms";
import {LoadMaskService} from "../services/load-mask.service";
import {ShippingService} from "../services/shipping.service";
import {Shipping} from "../interfaces/shipping-intetface";
import {ResolutionsService} from "../services/general/resolutions.service";
import {AccountingDocuments} from "../models/accounting-model";
import {FormatsService} from "../services/formats.service";
import {DocumentViewComponent} from "./document-view/document-view.component";

@Component({
  selector: 'app-profile-container',
  templateUrl: './documents.component.html',
  styleUrls: ['./documents.component.scss']
})
export class DocumentsContainerComponent extends BaseComponent  implements OnInit, AfterViewInit, OnDestroy  {
  @ViewChild('searchItems') searchItems: SearchDataComponent;
  @ViewChild('pagination') pagination: ExodoPaginationComponent;
  @ViewChild('documentView') documentView: DocumentViewComponent;
  showResults = false;
  dataDian: any = {};
  interval: any;
  process: ProcessSoftware;
  software: SoftwareTest;
  aDocuments: AccountingDocuments[]= [];
  public modalForm: FormGroup;
  public title = 'Documentos generados';
  public statusDocument = [
    {
      status_id   : -1,
      icon        : 'fas fa-check-double fas-fa-ok',
      description : 'Indiferente'
    },
    {
      status_id   : 0,
      icon        : 'fas fa-bug fas-fa-error',
      description : 'Documento sin validar'
    },
    {
      status_id   : 1,
      icon        : 'fas fa-thumbs-up fas-fa-ok-thumbs',
      description : 'Documento validado correctamente'
    }
  ];
  protected currentTypePersons: any;
  protected isClicked = false;
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
    private resSer: ResolutionsService,
    public format: FormatsService,
  ) {
    super(_token, router, translate);
    const currentDate = DateManager.currentDate();
    this.modalForm = this.fb.group({
      start_date: [DateManager.oldDate()],
      end_date: [currentDate],
      document_type: [0],
      document_status: [-1],
    });
  }

  ngOnInit(): void {
    const param = {
      where : '{"active":"1"}',
      limit : 30
    };
    
    this.resSer.getAccountingDocuments(param).subscribe((resp) => {
      this.aDocuments  = resp;
    });
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
      values.limit  = 30;
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
          this.onClickItem(this.shipping.shippingData[0]);
        }
        this.setPagination();
      },
      error: () => {
        this.shipping.shippingData = [];
        this.mask.hideBlockUI();
      }
    });
  }
  
  protected onClickItem(row: Shipping): void {
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
    this.modalForm.get('document_type').setValue(0);
    this.modalForm.get('document_status').setValue(-1);
    this.modalForm.get('start_date').setValue(DateManager.oldDate());
    this.modalForm.get('end_date').setValue(DateManager.currentDate());
  }
  private setPagination() {
    this.pagination.setPagination(this.shipping.shippingDataRecords);
  }
  
  onStatusDocument(trackId: string): void {
    this.settings.showBlockUI();
    this.api.post(`/status/document/${trackId}`).subscribe({
      next: (resp: any) => {
        const respDian  = resp.response as DianResponse;
        this.settings.hideBlockUI();
        this.dataDian = {
          ErrorMessage: respDian.ErrorMessage,
          StatusMessage: respDian.StatusMessage,
          IsValid: respDian.IsValid,
          StatusCode: respDian.StatusCode,
          StatusDescription: respDian.StatusDescription,
          XmlDocumentKey: respDian.XmlDocumentKey,
          XmlFileName: respDian.XmlFileName,
        }
        this.showResults = true;
      },
      error: (err) => {
        this.settings.hideBlockUI();
        this.msg.errorMessage('Error', err || err.message);
      }
    });
  }
  sendEmail(path: string, shipping: Shipping): void {
    const jsonData = shipping.jsonData;
    if (!jsonData?.customer?.email) {
      this.msg.errorMessage('Error', 'No se ha encontrado el correo del cliente para enviar el documento');
      return;
    }
    const params = {
      email_to: jsonData.customer.email,
    }
    this.settings.showBlockUI('Enviando documento por correo...');
    this.api.post(`/documents/${path}/${shipping.XmlDocumentKey}`, params).subscribe({
      next: (resp) => {
        this.settings.hideBlockUI();
        this.msg.toastMessage('Éxito', resp.message);
      },
      error: (err) => {
        this.settings.hideBlockUI();
        this.msg.errorMessage('Error', err);
      }
    });
  }
  onGetDocument(path: string, shipping: Shipping): void {
    this.settings.showBlockUI();
    this.api.post(`/documents/${path}/${shipping.XmlDocumentKey}`, {
      regenerate: 1,
    }).subscribe({
      next: (resp: any) => {
        this.settings.hideBlockUI();
        if (resp?.data || resp?.pdf) {
          const data = resp?.data || resp?.pdf?.data;
          DownloadFile.Pdf(data, `${shipping.document_number}.pdf` );
        } else if (resp?.content) {
          DownloadFile.Xml(resp?.content?.XmlBytesBase64, `${shipping.document_number}.xml`);
        } else if (resp?.attachedDocument?.data) {
          DownloadFile.Xml(resp?.attachedDocument?.data, `${shipping.XmlDocumentName}`);
        } else {
          this.msg.errorMessage('Error', 'No se ha podido descargar el archivo');
        }
      },
      error: (err) => {
        this.settings.hideBlockUI();
        this.msg.errorMessage('Error', err || err.message);
      }
    });
  }
  onCloseCard(): void {
    this.showResults = false;
    this.dataDian = {};
  }
  ngOnDestroy(): void {
    clearInterval(this.interval);
  }
  
  protected openDocument(row: Shipping) {
    if(!row.XmlDocumentKey) return;
    window.open(`https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=${row.XmlDocumentKey}`, '_blank');
  }
  
  getCurrency(item: Shipping) {
    return item?.jsonData?.currency || null;
  }
  
  protected documentStatusTooltipRender (row: Shipping): string {
    if (row.is_valid === 1) {
      return 'Documento validado por la DIAN.';
    } else {
      return 'Documento con errores en la validación por la DIAN.';
    }
  }
  
  protected documentStatusCellRender (row: Shipping): string {
    if (row.is_valid === 1) {
      return '<span class="span-button"><i class="fas fa-thumbs-up fas-fa-ok-thumbs fa-cursor"></i></span>';
    } else {
      return '<span class="span-button"><i class="fas fa-bug fas-fa-error fa-cursor"></i></span>';
    }
  }
}
