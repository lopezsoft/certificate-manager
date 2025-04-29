import {Component, ViewChild} from '@angular/core';
import {animate, style, transition, trigger} from "@angular/animations";
import {CertificateRequest, FileManager} from "../../interfaces/file-manager.interface";
import {ShippingService} from "../../services/shipping.service";
import {FormatsService} from "../../services/formats.service";
import {HttpResponsesService, MessagesService} from "../../utils";
import {DocumentStatusComments, DocumentStatusDescription, DocumentStatusEnum} from "../../common/enums/DocumentStatus";
import {convertBytesToMB} from "../../common/utils/conversion.helper";
import {LoadMaskService} from "../../services/load-mask.service";
import { jqxEditorComponent } from 'jqwidgets-ng/jqxeditor';

@Component({
  selector: 'app-request-in-process-view',
  templateUrl: './request-in-process-view.component.html',
  styleUrl: './request-in-process-view.component.scss',
  animations: [
    trigger('fadeInOut', [
      transition(':enter', [
        style({ opacity: 0 }),
        animate('300ms', style({ opacity: 1 })),
      ]),
      transition(':leave', [
        animate('300ms', style({ opacity: 0 })),
      ])
    ])
  ]
})
export class RequestInProcessViewComponent {
  @ViewChild('myEditor') myEditor: jqxEditorComponent;
  protected selectedFile: FileManager;
  protected readonly convertBytesToMB = convertBytesToMB;
  protected comments: string = null;
  protected readonly documentStatusDescription = DocumentStatusDescription;
  protected readonly DocumentStatusEnum = DocumentStatusEnum;
  protected canRejectRequest: boolean = false;
  constructor(
      public shipping: ShippingService,
      public format: FormatsService,
      protected http: HttpResponsesService,
      private  msg: MessagesService,
      private mask: LoadMaskService,
  ) {
  }

  initData() {
    // console.log('initData');
  }

  public get currentShipping(): CertificateRequest {
    return this.shipping.currentRequestAll;
  }

  protected sendEmail(status: DocumentStatusEnum) {
    this.mask.showBlockUI("Cambiando estado del documento...");
    this.http.post(`/certificate-request/${this.currentShipping.id}/send-mail`, {
      request_status: status,
      comments: this.comments ? this.comments : DocumentStatusComments[status],
      user_of_change: 'MANAGER'
    }).subscribe({
      next: () => {
        this.mask.hideBlockUI();
        this.msg.toastMessage('Éxito', 'Estado actualizado correctamente');
        this.shipping.currentRequestAll.request_status = status;
      },
      error: () => {
        this.mask.hideBlockUI();
      }
    });
  }

  protected updateStatus(status: DocumentStatusEnum) {
    this.msg.confirm("¿Está seguro de que desea cambiar el estado del documento?", "Por favor confirme su acción")
      .then((result) => {
        if (result.isConfirmed) {
          this.mask.showBlockUI("Cambiando estado del documento...");
          this.http.put(`/certificate-request/${this.currentShipping.id}/status`, {
            request_status: status,
            comments: this.comments ? this.comments : DocumentStatusComments[status],
            user_of_change: 'MANAGER'
          }).subscribe({
            next: (resp) => {
              this.mask.hideBlockUI();
              this.shipping.currentRequestAll.request_status = status;
              this.msg.toastMessage('Éxito', resp.message);
              this.canRejectRequest = false;
              this.comments = null;
            },
            error: () => {
              this.mask.hideBlockUI();
            }
          });

        }
      })
  }

  protected canSendEmail() {
    return this.currentShipping.request_status == DocumentStatusEnum.ACCEPTED ||
        this.currentShipping.request_status == DocumentStatusEnum.PENDING;
  }

  protected onDownload(file: FileManager) {
    const url = `${this.http.getAppUrl()}/attachments/${file.file_path}`;
    this.http.openDocument(url);
  }

  protected selectFile(file: FileManager) {
    this.selectedFile = file;
  }

  protected canReject() {
    const currentShipping = this.currentShipping;
    return currentShipping.request_status === DocumentStatusEnum.PROCESSING ||
      currentShipping.request_status === DocumentStatusEnum.SENT;
  }

  protected onRejectRequest() {
    this.updateStatus(DocumentStatusEnum.REJECTED);
  }

  rejectRequest() {
    this.canRejectRequest = true;
    setTimeout(() => {
      this.myEditor.focus();
    }, 10);
  }
}
