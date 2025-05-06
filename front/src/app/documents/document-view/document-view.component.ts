import {Component, ElementRef, ViewChild} from '@angular/core';
import {animate, style, transition, trigger} from "@angular/animations";
import {ShippingService} from "../../services/shipping.service";
import {FormatsService} from "../../services/formats.service";
import {CertificateRequest, FileManager} from "../../interfaces/file-manager.interface";
import {convertBytesToMB} from "../../common/utils/conversion.helper";
import {HttpResponsesService, MessagesService} from "../../utils";
import {
	DocumentStatusComments,
	DocumentStatusDescription,
	DocumentStatusEnum,
	FileDocumentTypeEnum
} from "../../common/enums/DocumentStatus";
import {LoadMaskService} from "../../services/load-mask.service";
import {Router} from "@angular/router";
import {DocumentViewerService} from "../../services/document-viewer.service";

@Component({
	selector: 'app-document-view',
	templateUrl: './document-view.component.html',
	styleUrl: './document-view.component.scss',
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
export class DocumentViewComponent {
	@ViewChild('fileUploadCc', { static: false}) fileUploadCc: ElementRef;
	protected selectedFile: FileManager;
	protected readonly convertBytesToMB = convertBytesToMB;
	protected readonly documentStatusDescription = DocumentStatusDescription;
	protected readonly DocumentStatusEnum = DocumentStatusEnum;
	protected readonly DocumentStatusComments = DocumentStatusComments;
	protected canAddFile: boolean;
	protected files = [];
	protected formData: FormData;
	protected comments: string = null;
	constructor(
		public shipping: ShippingService,
		public format: FormatsService,
		protected http: HttpResponsesService,
		protected documentViewerService: DocumentViewerService,
		private  msg: MessagesService,
		private mask: LoadMaskService,
		private router: Router,
	) {
	}

	initData() {
		// console.log('initData');
	}

	public get currentShipping(): CertificateRequest {
		return this.shipping.currentShipping;
	}

	protected updateStatus(status: DocumentStatusEnum) {
		this.msg.confirm("¿Está seguro de que desea cambiar el estado del documento?", "Por favor confirme su acción")
			.then((result) => {
				if (result.isConfirmed) {
					this.mask.showBlockUI("Cambiando estado del documento...");
					this.http.put(`/certificate-request/${this.currentShipping.id}/status`, {
						request_status: status,
						comments: this.comments ? this.comments : DocumentStatusComments[status],
						user_of_change: 'USER'
					}).subscribe({
						next: () => {
							this.mask.hideBlockUI();
							this.shipping.currentShipping.request_status = status;
						},
						error: () => {
							this.mask.hideBlockUI();
						}
					});

				}
			})
	}

	protected canSendEmail() {
		return this.currentShipping.request_status == DocumentStatusEnum.DRAFT
			|| this.currentShipping.request_status == DocumentStatusEnum.PENDING
			|| this.currentShipping.request_status == DocumentStatusEnum.REJECTED;
	}

	onDownload(file: FileManager) {
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

	protected onDeleteFile(file: FileManager) {
		this.msg.confirm("¿Está seguro de que desea eliminar el archivo?", "Por favor confirme su acción")
			.then((result) => {
				if (result.isConfirmed) {
					this.mask.showBlockUI("Eliminando archivo...");
					this.http.delete(`/certificate-request/${this.currentShipping.id}/files/${file.id}`).subscribe({
						next: (resp) => {
							this.mask.hideBlockUI();
							this.currentShipping.files = this.currentShipping.files.filter(f => f.id !== file.id);
							this.selectedFile = null;
							this.msg.toastMessage('Éxito', resp.message);
						},
						error: () => {
							this.mask.hideBlockUI();
						}
					});
				}
			})
	}

	onEdit() {
		this.router.navigate(['/requests/list/edit', this.currentShipping.id]);
	}

	onAddFile() {
		this.canAddFile = true;
	}

	onUploadCC() {
		const fileUpload = this.fileUploadCc.nativeElement;
		const file = fileUpload.files[0];
		// Check file size and type 1000kb = 1000000
		if (file.size > 1000000) { // 1000kb
			this.fileUploadCc.nativeElement.value = '';
			const size = (file.size / 1024).toFixed(2); // Convert to KB
			this.msg.errorMessage('',`El archivo no debe ser mayor a 1000kb. Tamaño del archivo ${size}kb.`);
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
		this.formData = new FormData();
		this.files.forEach((file) => {
			this.formData.append('file', file.data);
		});

		this.mask.showBlockUI("Subiendo archivo...");
		this.http.post(`/certificate-request/${this.currentShipping.id}/files`, this.formData)
			.subscribe({
			next: (resp: any) => {
				this.currentShipping.files.push(resp.dataRecords.data[0]);
				this.mask.hideBlockUI();
				this.msg.toastMessage('Éxito', resp.message);
				this.files = [];
				this.canAddFile = false;
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
