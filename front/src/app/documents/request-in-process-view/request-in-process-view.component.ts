import {Component, ElementRef, ViewChild} from '@angular/core';
import {animate, style, transition, trigger} from "@angular/animations";
import {CertificateRequest, FileManager} from "../../interfaces/file-manager.interface";
import {ShippingService} from "../../services/shipping.service";
import {FormatsService} from "../../services/formats.service";
import {HttpResponsesService, MessagesService} from "../../utils";
import {
	DocumentStatusComments,
	DocumentStatusDescription,
	DocumentStatusEnum,
	FileDocumentTypeEnum
} from "../../common/enums/DocumentStatus";
import {convertBytesToMB} from "../../common/utils/conversion.helper";
import {LoadMaskService} from "../../services/load-mask.service";
import {jqxEditorComponent} from 'jqwidgets-ng/jqxeditor';
import {DocumentViewerService} from "../../services/document-viewer.service";

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
	@ViewChild('fileUploadZip', { static: false}) fileUploadZip: ElementRef;
	pin: string;
	protected selectedFile: FileManager;
	protected readonly convertBytesToMB = convertBytesToMB;
	protected comments: string = null;
	protected readonly documentStatusDescription = DocumentStatusDescription;
	protected readonly DocumentStatusEnum = DocumentStatusEnum;
	protected canRejectRequest: boolean = false;
	protected canAddFile: boolean;
	protected files = [];
	protected formData: FormData;
	constructor(
		public shipping: ShippingService,
		public format: FormatsService,
		protected http: HttpResponsesService,
		protected documentViewerService: DocumentViewerService,
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
		if (file.extension_file === 'pdf') {
			this.documentViewerService.open(url, this.currentShipping.company_name);
		} else {
			this.http.openDocument(url);
		}
	}

	protected selectFile(file: FileManager) {
		this.selectedFile = file;
	}

	protected canReject() {
		const currentShipping = this.currentShipping;
		return currentShipping.request_status === DocumentStatusEnum.PROCESSING ||
			currentShipping.request_status === DocumentStatusEnum.SENT ||
			currentShipping.request_status === DocumentStatusEnum.ACCEPTED;
	}

	protected onRejectRequest() {
		this.updateStatus(DocumentStatusEnum.REJECTED);
	}

	protected rejectRequest() {
		this.canRejectRequest = true;
		setTimeout(() => {
			this.myEditor.focus();
		}, 10);
	}

	onAddFile() {
		this.canAddFile = true;
	}

	onUploadZip() {
		const fileUpload = this.fileUploadZip.nativeElement;
		const file = fileUpload.files[0];
		if (file.size > 100000) { // 100kb
			this.fileUploadZip.nativeElement.value = '';
			const size = (file.size / 1024).toFixed(2); // Convert to KB
			this.msg.errorMessage('',`El archivo no debe ser mayor a 100kb. Tamaño del archivo ${size}kb.`);
		} else if (file.size === 0) {
			this.fileUploadZip.nativeElement.value = '';
			this.msg.errorMessage('', 'El archivo no puede estar vacío');
		} else if (file.type !== 'application/x-zip-compressed') {
			this.fileUploadZip.nativeElement.value = '';
			this.msg.errorMessage('', 'El archivo debe ser un ZIP');
		} else {
			this.files = [];
			this.files.push({ data: file, inProgress: false, progress: 0});
		}
	}

	onUploadFile() {
		if (this.files.length === 0) {
			this.msg.errorMessage('', 'No hay archivos para subir');
			return;
		}
		if (this.pin === undefined || this.pin === null || this.pin === '') {
			this.msg.errorMessage('', 'Por favor ingrese el PIN del certificado');
			return;
		}
		this.formData = new FormData();
		this.files.forEach((file) => {
			this.formData.append('file', file.data);
		});
		this.formData.append('pin', this.pin);
		this.formData.append('document_type', 'CERTIFICATE');

		this.mask.showBlockUI("Subiendo archivo...");
		this.http.post(`/certificate-request/${this.currentShipping.id}/files`, this.formData)
			.subscribe({
				next: (resp: any) => {
					this.currentShipping.files.push(resp.dataRecords.data[0]);
					this.mask.hideBlockUI();
					this.msg.toastMessage('Éxito', resp.message);
					this.files = [];
					this.canAddFile = false;
					this.pin = null;
				},
				error: () => {
					this.mask.hideBlockUI();
				}
			});
	}
	protected existFileZip(): boolean {
		return this.currentShipping.files.some((file) => {
			return file.document_type === FileDocumentTypeEnum.CERTIFICATE;
		});
	}

	protected getFiles(): FileManager[] {
		return this.currentShipping.files.filter((file) => {
			return file.document_type === FileDocumentTypeEnum.ATTACHED;
		});
	}

	protected getFilesZip(): FileManager[] {
		return this.currentShipping.files.filter((file) => {
			return file.document_type === FileDocumentTypeEnum.CERTIFICATE;
		});
	}
}
