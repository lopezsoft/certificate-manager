import {AfterViewInit, Component, OnDestroy, OnInit, ViewChild} from '@angular/core';
import {ExodoGridComponent} from "exodolibs";
import {HttpResponsesService, MessagesService} from "../../utils";
import TokenService from "../../utils/token.service";
import {ActivatedRoute, Router} from "@angular/router";
import {TranslateService} from "@ngx-translate/core";
import {DianResponse, ProcessSoftware, ResponseDian, SoftwareTest} from "../../models/general-model";
import {CustomGridComponent} from "../../@core/data/custom-grid/custom-grid.component";
import {GlobalSettingsService} from "../../services/global-settings.service";

@Component({
  selector: 'app-software-test-list',
  templateUrl: './software-test-list.component.html',
  styleUrls: ['./software-test-list.component.scss']
})
export class SoftwareTestListComponent extends CustomGridComponent  implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('exodoGrid', { static: false }) exodoGrid: ExodoGridComponent;
  showResults = false;
  dataDian: any = {};
  interval: any;
  isProcess = false;
  process: ProcessSoftware;
  software: SoftwareTest;
  displayProcess = false;
  constructor(public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              public settings: GlobalSettingsService
              ) {
    super(msg, api, _token, router, translate, aRouter);
  }
  ngOnInit(): void {
    this.useOtherButton   = true;
    this.textOtherButton  = 'Volver a Software';
    this.faOtherButton    = 'fas fa-arrow-left fas-fa-close';
    this.showEditButton   = false;
    this.showDeleteButton = false;
    this.useNewButton     = false;
    this.columns    = [
      {
        text: '',
        dataIndex: '#error#',
        width: '16px',
        tooltip: 'Ver errores',
        tooltipDirection: 'left',
        cellRender: (): string => {
          return `<span class="span-button">
            <i class="fas fa-bug fa-cursor fas-fa-error"></i>
          </span>`;
        },
        cellClick: (row: SoftwareTest): void => {
          if (row?.process.error_message && this.isAvailable()) {
            this.dataDian = row.process.error_message;
            this.showResults = true;
          }
        }
      },
      {
        text: '',
        dataIndex: '#zipkey#',
        width: '16px',
        tooltip: 'Consultar estado en la DIAN (Zipkey)',
        tooltipDirection: 'left',
        cellRender: (): string => {
          return `<span class="span-button">
            <i class="fas fa-sync fa-cursor fas-fa-ok"></i>
          </span>`;
        },
        cellClick: (row: SoftwareTest): void => {
          this.onZipkey(row.zipkey);
        }
      },
      {
        text: '',
        dataIndex: '#cude#',
        width: '16px',
        tooltip: 'Consultar estado en la DIAN (CUFE/CUDE)',
        tooltipDirection: 'left',
        cellRender: (): string => {
          return `<span class="span-button">
            <i class="fas fa-sync-alt fa-cursor fas-fa-ok"></i>
          </span>`;
        },
        cellClick: (row: SoftwareTest): void => {
          this.onStatusTest(row.XmlDocumentKey);
        }
      },
      {
        text: 'ID Prueba',
        dataIndex: 'process_id',
        align: "right",
        cellRender: (row: SoftwareTest): string => {
            return `<a href="/#/settings/software/process/${row.process_id}">${row.process_id.toString().padStart(9, '0')}</a>`;
        }
      },
      {
        text: 'Tipo de documento',
        dataIndex: 'document.voucher_name',
        width: '200px',
        cellRender: (row: SoftwareTest): string => {
          return row.document?.voucher_name;
        }
      },
      {
        text: 'Nº. Documento',
        dataIndex: 'document_number',
        align: "right"
      },
      {
        text: 'Tipo',
        dataIndex: 'typeDescription',
        cellRender: (row: SoftwareTest): string => {
          return row.software?.typeDescription;
        }
      },
      {
        text: 'Estado de pruebas',
        dataIndex: 'processStatusDescription',
        width: '200px',
        cellRender: (row: SoftwareTest): string => {
          return `<div class="shadaw status status--${row.software.test_process_status}">${row.software.processStatusDescription}</div>`;
        }
      },
      {
        text: 'Zip key',
        dataIndex: 'zipkey',
        width: 'auto',
      },
      {
        text: 'CUFE/CUDE',
        dataIndex: 'XmlDocumentKey',
        width: '100%',
      },
    ];
    super.ngOnInit();
    this.crudApi = {
      create: '/software',
      read: '/software',
      update: '/software/',
      delete: '/software/',
    };
    this.title = 'Lista de las pruebas de Software';
  }

  ngAfterViewInit(): void {
    const ts  = this;
    const uid       = ts.aRouter.snapshot.paramMap.get('id');
    const processId = ts.aRouter.snapshot.paramMap.get('processId');
    if (uid){
      this.crudApi.read = `/software/test/${uid}`;
    } else if(processId){
      this.crudApi.read = `/software/process/${processId}`;
      this.isProcess    = true;
    }
    this.exodoGrid.proxy.api = {
      read: `${this.api.getUrl()}${this.crudApi.read}`,
    };
    this.exodoGrid.onLoad();
    this.exodoGrid.onAfterRefreshLoad((dataRecords) => {
      if (this.displayProcess) {
        const data: SoftwareTest[] = dataRecords?.data ?? [];
        if (data.length > 0) {
          const row     = data[0];
          this.software = row;
          this.process  = row?.process;
        }
      }
    });
    if (this.isProcess) {
      this.verifyStatus();
      this.displayProcess = true;
    }
  }
  onZipkey(trackId: string): void {
    this.settings.showBlockUI();
    this.api.post(`/status/zip/${trackId}`).subscribe({
      next: (resp: any) => {
        const data      = resp.ResponseDian as ResponseDian;
        const respDian  = data.Envelope.Body.GetStatusZipResponse.GetStatusZipResult.DianResponse;
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
        this.msg.errorMessage('Error', err.error?.message || err.message);
      }
    });
  }
  onStatusTest(trackId: string): void {
    this.settings.showBlockUI();
    this.api.post(`/status/document/test/${trackId}`).subscribe({
      next: (resp: any) => {
        const respDian  = resp.ResponseDian as DianResponse;
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
        this.msg.errorMessage('Error', err.error?.message || err.message);
      }
    });
  }
  onCloseCard(): void {
    this.showResults = false;
    this.dataDian = {};
  }
  onOtherButton(): void {
    this.router.navigate(['/settings/software']);
  }
  isAvailable(): boolean {
    return (this.isProcess);
  }
  verifyStatus(): void {
    this.interval = setInterval(() => {
      if (this.isAvailable()) {
        const data: SoftwareTest[] = this.exodoGrid.getDataSource().dataRecords?.data ?? [];
        if (data.length > 0) {
          const row     = data[0];
          this.software = row;
          this.process  = row?.process;
          if (data.length === 0 || !( row?.process?.state === 'FINISHED' || row?.process?.state === 'ERROR')) {
            this.displayProcess = true;
            this.exodoGrid.searchQuery(this.exodoGrid.getSearchFieldValue());
          } else {
            this.displayProcess = false;
            clearInterval(this.interval);
          }
        }
      }
    }, 5000);
  }
  ngOnDestroy(): void {
    clearInterval(this.interval);
  }
}
