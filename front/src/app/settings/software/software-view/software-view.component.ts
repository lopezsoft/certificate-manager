import {Component, EventEmitter, Output} from '@angular/core';
import {FormatsService} from "../../../services/formats.service";
import {SoftwareService} from "../../../services/general/software.service";
import {DianResponse, Software, StringMap} from "../../../models/general-model";
import { Router } from '@angular/router';
import {ErrorResponse} from "../../../interfaces";
import {HttpResponsesService, MessagesService} from "../../../utils";
import {LoadMaskService} from "../../../services/load-mask.service";
import {GlobalSettingsService} from "../../../services/global-settings.service";

@Component({
  selector: 'app-software-view',
  templateUrl: './software-view.component.html',
  styleUrl: './software-view.component.scss'
})
export class SoftwareViewComponent {
  @Output() onAfterRunningTest = new EventEmitter<Software>();
  protected technicalKeyList = new Set([1,4]);
  protected data      : any = null;
  protected dataDian: any = {};
  constructor(
      public software: SoftwareService,
      public format: FormatsService,
      public router: Router,
      public msg: MessagesService,
      public mask: LoadMaskService,
      public api: HttpResponsesService,
      public settings: GlobalSettingsService,
  ) {
  }

  initData() {
    this.data = null;
    this.dataDian = null;
  }

  public get currentSoftware(): Software {
    return this.software.currentSoftware;
  }

  protected editSoftware() {
    this.settings.isSoftwareEnabled = false;
    localStorage.setItem('oldRoute', this.router.url);
    this.router.navigate([`/settings/software/${this.currentSoftware.id}/edit`]);
  }

  onGetTechnicalKey() {
    this.mask.showBlockUI('Consultando clave técnica.');
    this.data   = '';
    this.software.getNumberingRange({
      type_id: this.currentSoftware.type_id,
    }).
    subscribe({
      next: (resp) => {
        this.mask.hideBlockUI();
        const
            result = resp.ResponseDian.Envelope.Body.GetNumberingRangeResponse.GetNumberingRangeResult;
        this.data = result.ResponseList;
        if(result.OperationCode === "100") {
          this.msg.toastMessage('Clave técnica', result.OperationDescription);
        }else {
          this.msg.errorMessage('Clave técnica', result.OperationDescription);
        }
      },
      error: (err: ErrorResponse) => {
        this.mask.hideBlockUI();
        this.msg.errorMessage('Error', err.error?.message || err.message);
      }
    });
  }
  isFinishing(): boolean {
    return parseFloat(this.currentSoftware.environment_id.toString()) === 1;
  }
  isTestSetIdValid(): boolean {
    return this.currentSoftware?.testsetid?.length > 30;
  }

  onRunTest() {
    this.initData();
    if (!this.isTestSetIdValid()) {
      this.msg.errorMessage('Error', 'El test set id no es válido.');
      return;
    }
    if (this.isFinishing()) {
      this.msg.confirm('Habilitación', 'El software ya se encuentra habilitado. <br> ¿Desea iniciar una nueva habilitación?')
          .then((result) => {
            if (result.isConfirmed) {
              this.onRunningTest();
            }
          });
    } else {
      this.onRunningTest();
    }

  }
  private onRunningTest() {
    this.mask.showBlockUI('Inicializando habilitación.');
    this.currentSoftware.test_process_status = 'CREATED';
    this.currentSoftware.processStatusDescription = 'CREADO';
    this.currentSoftware.environment_id = 2;
    this.api.post('/document/run-test',{
      software_id: this.currentSoftware.id,
    })
        .subscribe({
          next: (resp: any) => {
            this.settings.isSoftwareEnabled = true;
            this.mask.hideBlockUI();
            this.msg.toastMessage('', resp.message);
            this.onAfterRunningTest.emit(this.currentSoftware);
          },
          error: () => {
            this.mask.hideBlockUI();
            this.currentSoftware.test_process_status = 'ERROR';
            this.currentSoftware.processStatusDescription = 'ERROR';
          }
        });
  }
  isProduction(): boolean {
    return this.currentSoftware.environment_id.toString() === '1';
  }

  protected hasTechnicalKey(): boolean {
    return this.technicalKeyList.has(parseInt(this.currentSoftware.type_id.toString()));
  }
  protected onFinished() {
    this.initData();
    const records = {
      environment_id: 1,
      testsetid: '',
      test_process_status: 'FINISHED',
    }
    this.mask.showBlockUI('Finalizando habilitación.');
    this.api.put(`/software/${this.currentSoftware.id}`, {
      records: JSON.stringify(records)
    }).
    subscribe({
      next: (resp) => {
        this.mask.hideBlockUI();
        this.msg.onMessage('Finalización', resp.message);
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      },
      error: () => {
        this.mask.hideBlockUI();
      }
    });
  }

  protected getProcessData() {
    const curr = this.software.currentSoftware.test_process;
      return {
        id: curr.id,
        software_id: curr.software_id,
        uuid: curr.uuid,
        status: curr.status,
        status_description: curr.status_description,
        error_message: curr.error_message,
        StatusDescription: curr.StatusDescription,
      }
  }

  protected onStatusTest(trackId: string): void {
    this.dataDian = null;
    this.settings.showBlockUI();
    this.api.post(`/status/zip/${trackId}`).subscribe({
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
      },
      error: () => {
        this.settings.hideBlockUI();
      }
    });
  }

  protected getEnvironment() {
    const item = this.software.currentSoftware;
    if (item.type_id?.toString() == '3' || item.environment.id == 1) {
      return item.environment.environment_name
    }
    return item?.test_process?.status ? item.environment.environment_name : 'Desconocido';
  }
}
