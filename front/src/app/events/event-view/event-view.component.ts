import {Component, ElementRef, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {DataSourceContract, ExodoGridComponent} from 'exodolibs';
import {NgbModalRef} from '@ng-bootstrap/ng-bootstrap';
import {HttpResponsesService, MessagesService} from '../../utils';
import {ActivatedRoute, Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';
import {AuthenticationService} from '../../services/users';
import {DocumentEventInterface} from '../../interfaces/events';
import {EventsService} from '../../services/events/events.service';
import {ColumnContract} from 'exodolibs/lib/components/grid/contracts';
import {DownloadFile} from '../../common/class/download-file';
import {DocumentStatusEnum} from '../../common/enums/DocumentStatus';
import {BaseComponent} from "../../@core/components/base/base.component";
import {LoadMaskService} from "../../services/load-mask.service";
import TokenService from "../../utils/token.service";

@Component({
  selector: 'app-event-view',
  templateUrl: './event-view.component.html',
  styleUrls: ['./event-view.component.scss']
})
export class EventViewComponent extends BaseComponent implements OnInit, OnDestroy {
  @ViewChild('exodoGrid', {static: false}) exodoGrid: ExodoGridComponent;
  @ViewChild('content') content: ElementRef;
  @ViewChild('contentMovement') contentMovement: ElementRef;
  private interval: any;
  public title = 'Eventos de la factura electrónica';
  public modal: NgbModalRef;
  public columns: ColumnContract[] = [];
  public dataSource: DataSourceContract = {
    rows: []
  };
  loading: boolean = false;
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
    super(_token, router, translate);
  }

  ngOnInit(): void {
    const ts = this;
    ts.columns =
      [
        {
          text: '#',
          dataIndex: '#',
          width: '16px',
          align: 'right',
          cellRender: (row, rowIndex): string => {
            return '<b>' + (rowIndex + 1).toString() + '</b>';
          }
        },
        {
          text: '...',
          dataIndex: 'id',
          width: '64px',
          tooltip: 'Descargar respuesta XML de la DIAN',
          tooltipDirection: 'left',
          cellRender: (): string => {
            return '<span class="span-button fa-cursor fas-fa-mail"><i class="fas fa-download"></i></span>';
          },
          cellClick: (row: DocumentEventInterface): void => {
            this.downloadXml(row);
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
          cellClick: (): void => {
            window.open(`https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=${this.eventService.receptionDocument.cufe_cude}`, '_blank');
          }
        },
        {
          text: '',
          dataIndex: '#mail_#',
          width: '16px',
          tooltip: 'Enviar correo',
          tooltipRender: (row: DocumentEventInterface): string => {
            return `${row.send_mail > 0 ? 'Reenviar' : 'Enviar'} correo`;
          },
          cellRender: (row: DocumentEventInterface): string => {
            return `<span class="span-button">
            <i class="fas fa-paper-plane fa-cursor ${row.send_mail > 0 ? 'fas-fa-mail-send' : 'fas-fa-mail'}">
          </span>`;
          },
          cellClick: (row: DocumentEventInterface): void => {
            if (row.send_mail >= 4) {
              this.msg.toastMessage('', 'No es posible enviar más de 4 correos');
              return;
            }
            this.mask.showBlockUI('Enviando correo');
            this.eventService.sentEventMail(row.id)
              .subscribe({
                next: () => {
                  this.mask.hideBlockUI();
                  this.msg.toastMessage('', 'Correo enviado correctamente');
                  row.send_mail += 1;
                },
                error: (err) => {
                  this.mask.hideBlockUI();
                  console.log(err);
                }
              });
          }
        },
        {
          text: 'Código',
          dataIndex: 'type_event.code',
          align: 'center',
          cellRender: (row: DocumentEventInterface): string => {
            return row.type_event.code;
          }
        },
        {
          text: 'Folio', dataIndex: 'prefix', align: 'right', width: '120',
          cellRender: (row: DocumentEventInterface): string => {
            return `<div class="cell-line-height">
                  <span class="d-block text-nowrap font-small"><b>${row?.resolution?.prefix ?? ''}<b/>${row.event_number}</span>
                </div>`;
          }
        },
        {text: 'Fecha del evento', dataIndex: 'date_event'},
        {
          text: 'Descripción', dataIndex: 'description', width: '100%',
          cellRender: (row: DocumentEventInterface): string => {
            return row.type_event.name;
          }
        },
        {
          text: 'Estado del evento',
          dataIndex: 'document_status',
          align: 'center',
          width: '64px',
          cellRender: (row: DocumentEventInterface): string => {
            return '<span class="document-status-' + row.document_status + '">' + row.statusDescription + '</span>';
          }
        },
      ];
    super.ngOnInit();
    const id = this.aRouter.snapshot.paramMap.get('id');
    if (id) {
      this.mask.showBlockUI('Cargando eventos');
      this.search(Number(id));
      this.interval = setInterval(() => {
        this.search(Number(id));
      }, 30000);
    }
  }

  search(id: number): void {
    this.eventService.getEventsById(id)
      .subscribe({
        next: (data) => {
          this.mask.hideBlockUI();
          this.dataSource.rows = data.events;
          this.title = `Eventos de la factura electrónica ${data.folio}`;
        },
        error: () => {
          this.mask.hideBlockUI();
        }
      });
  }

  sendEvent(code: string, notes: string = ''): void {
    this.msg.confirm('Enviar evento', '¿Está seguro de enviar el evento?')
      .then((result) => {
        if (result.isConfirmed) {
          this.mask.showBlockUI('Enviando evento');
          this.eventService.sendEvent({code, notes}, this.eventService.receptionDocument.cufe_cude)
            .subscribe({
              next: () => {
                this.mask.hideBlockUI();
                this.msg.toastMessage('', 'Evento enviado correctamente');
              },
              error: () => {
                this.mask.hideBlockUI();
              }
            });
        }
      });
  }
  downloadXml(row: DocumentEventInterface): void {
    let eventData = row.event_data;
    if (typeof eventData === 'string') {
      eventData = JSON.parse(row.event_data as any);
    }
    DownloadFile.Xml(eventData.XmlBase64Bytes, `${eventData.XmlFileName}.xml`);
  }
  ngOnDestroy(): void {
    clearInterval(this.interval);
  }

  /**
   * Verifica si un evento está disponible para ser enviado a la DIAN, es decir, si no ha sido enviado anteriormente
   * @param code
   */
  isAvailable(code: string): boolean {
    const events = this.eventService?.receptionDocument?.events;
    if (!events) return false;
    return events.filter((event) => event.type_event.code === code).length === 0;
  }

  /**
   * Verifica si un evento ya existe
   * @param code
   */
  isExistent(code: string): boolean {
    const events = this.eventService?.receptionDocument?.events;
    if (!events) return false;
    return events.filter((event) => event.type_event.code === code && event.document_status === DocumentStatusEnum.ACCEPTED ).length > 0;
  }
  isCredit() {
    return this.eventService?.receptionDocument?.payment_method_id === 2;
  }

  goToParent() {
    this.router.navigate(['events/reception']);
  }

  getMaxEvents(): number {
    let total = 3;
    if (!this.isCredit()) {
      total = 1;
    }
    return total;
  }

  getValueEvents(): number {
    let total = 0;
    const events = this.eventService?.receptionDocument?.events;
    if (events) {
      total = events.filter((event) => event.document_status === DocumentStatusEnum.ACCEPTED )?.length ?? 0;
    }
    return total;
  }
}
