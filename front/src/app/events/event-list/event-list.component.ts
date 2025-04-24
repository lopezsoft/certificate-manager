import {AfterViewInit, Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {ExodoGridComponent} from 'exodolibs';
import {NgbModalRef} from '@ng-bootstrap/ng-bootstrap';
import {HttpResponsesService, MessagesService} from '../../utils';
import {ActivatedRoute, Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';
import {AuthenticationService} from '../../services/users';
import {DocumentReception} from '../../interfaces/events';
import {DownloadFile} from '../../common/class/download-file';
import {EventsService} from '../../services/events/events.service';
import {CustomGridComponent} from "../../@core/data/custom-grid/custom-grid.component";
import TokenService from "../../utils/token.service";
import {LoadMaskService} from "../../services/load-mask.service";
import {people} from "ionicons/icons";

@Component({
  selector: 'app-event-list',
  templateUrl: './event-list.component.html',
  styleUrls: ['./event-list.component.scss']
})
export class EventListComponent extends CustomGridComponent implements OnInit, AfterViewInit {
  @ViewChild('exodoGrid', {static: false}) exodoGrid: ExodoGridComponent;
  @ViewChild('content') content: ElementRef;
  @ViewChild('contentMovement') contentMovement: ElementRef;
  public title = 'Documentos recepcionados';
  public modal: NgbModalRef;

  constructor(public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              public auth: AuthenticationService,
              public mask: LoadMaskService,
              public eventService: EventsService,
  ) {
    super(msg, api, _token, router, translate, aRouter);
  }

  ngOnInit(): void {
    this.changeLanguage(this.activeLang);
    const ts = this;
    ts.useImport = true;
    ts.useOtherButton = true;
    ts.textOtherButton = 'Descargar plantilla';
    ts.faOtherButton = 'fas fa-cloud-download-alt mr-1 fas-fa-22';
    ts.columns =
      [
        {
          text: '...',
          dataIndex: 'id',
          width: '64px',
          tooltip: 'Ver eventos asociados',
          tooltipDirection: 'left',
          cellRender: (): string => {
            return '<span class="span-button fa-cursor fas-fa-mail"><i class="fas fa-tasks"></i></span>';
          },
          cellClick: (row: DocumentReception): void => {
            this.goRoute(`events/reception/events/${row.id}`);
          }
        },
        {
          text: '...',
          dataIndex: 'id',
          width: '64px',
          tooltip: 'Ver documento en la DIAN',
          tooltipDirection: 'left',
          cellRender: (): string => {
            return '<span class="span-button fa-cursor fas-fa-edit"><i class="fas fa-external-link-alt"></i></span>';
          },
          cellClick: (row: DocumentReception): void => {
            window.open(`https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=${row.cufe_cude}`, '_blank');
          }
        },
        {
          text: '...',
          dataIndex: 'id',
          width: '64px',
          tooltip: 'Descargar eventos XML',
          tooltipDirection: 'left',
          cellRender: (): string => {
            return '<span class="span-button fa-cursor fas-fa-mail"><i class="fas fa-download"></i></span>';
          },
          cellClick: (row: DocumentReception): void => {
            this.downloadXml(row);
          }
        },
        {
          text: 'Tipo de documento',
          dataIndex: 'document_type.name',
          width: '100%',
          cellRender: (row: DocumentReception): string => {
            return `<div class="d-flex align-items-center">
                 <div class="cell-line-height">
                  <span class="font-weight-bold d-block text-nowrap font-small">${row.document_type.voucher_name}</span>
                  <span class="text-muted font-small-3"><b>${row.payment_method.payment_method}</b></span>
                </div>
            </div>`;
          }
        },
        {
          text: 'Folio', dataIndex: 'folio', align: 'right', width: '120',
        },
        {text: 'Fecha de emisión', dataIndex: 'issue_date'},
        {
          text: 'Total',
          dataIndex: 'total',
          align: 'right',
          width: '120',
          format: 'es-CO',
          currency: 'COP',
          type: 'currency'
        },
        {
          text: 'Emisor',
          dataIndex: 'issuer_name',
          width: '100%',
          cellRender: (row: DocumentReception): string => {
            const name  = row.people?.company_name?.trim().toUpperCase() ?? '';
            const doc   = row?.people?.dni ?? '';
            return `<div class="d-flex align-items-center">
                <div class="avatar mr-1 ml-0 bg-light-success">
                    <div class="avatar-content">${name.charAt(0)}${name.charAt(1)}</div>
                </div>
                 <div class="cell-line-height">
                  <span class="font-weight-bold d-block text-nowrap font-small">${name}</span>
                  <span class="text-muted font-small-3"><b>NIT:</b> ${doc} </span>
                </div>
            </div>`;
          }
        },
        {text: 'CUFE/CUDE', dataIndex: 'cufe_cude', width: '100%'},
      ];
    super.ngOnInit();
    ts.crudApi = {
      create: '/events/document-receptions',
      read: '/events/document-receptions',
      update: '/events/document-receptions/',
      delete: '/events/document-receptions/'
    };
  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
  }

  createData(): void {
    const ts = this;
    super.createData();
    ts.goRoute('events/reception/event-create');
  }
  importData() {
    super.importData();
    this.goRoute(`events/reception/import`);
  }
  downloadXml(row: DocumentReception): void {
    this.mask.showBlockUI('Descargando XML');
    this.eventService.getEventStatus(row.cufe_cude)
      .subscribe({
        next: (resp) => {
          this.mask.hideBlockUI();
          const eventData = resp.ResponseDian;
          if (eventData.IsValid === 'true') {
            this. msg.toastMessage('', eventData.StatusMessage);
            DownloadFile.Xml(eventData.XmlBase64Bytes, `${eventData.XmlFileName}.xml`);
          } else {
            this.msg.toastMessage('', eventData.StatusDescription, 2);
          }
        },
        error: () => {
          this.mask.hideBlockUI();
        }
      });
  }
}
