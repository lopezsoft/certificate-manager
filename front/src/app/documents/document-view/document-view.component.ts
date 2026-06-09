import {Component, ElementRef, ViewChild, Output, EventEmitter} from '@angular/core';
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
import {IssuanceService} from "../../services/issuance.service";
import {IssuanceStatus, IssuanceDownloadMeta} from "../../interfaces/issuance.interface";
import {DebugService} from "../../utils/debug.service";

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
    ],
    standalone: false
})
export class DocumentViewComponent {
	@ViewChild('fileUploadCc', { static: false}) fileUploadCc: ElementRef;
	@ViewChild('fileUploadPayment', { static: false}) fileUploadPayment: ElementRef;
	@Output() onDeleted = new EventEmitter<void>();
	protected selectedFile: FileManager;
	protected readonly convertBytesToMB = convertBytesToMB;
	protected readonly documentStatusDescription = DocumentStatusDescription;
	protected readonly DocumentStatusEnum = DocumentStatusEnum;
	protected readonly DocumentStatusComments = DocumentStatusComments;
	protected canAddFile: boolean;
	protected files = [];
	protected formData: FormData;
	protected comments: string = null;
	protected canAddPaymentFile: boolean = false;
	protected document_type: string = null;

	/** Emisión de certificados */
	protected issuanceStatus: IssuanceStatus | null = null;
	protected issuanceDownloadMeta: IssuanceDownloadMeta | null = null;
	protected issuanceLoading = false;
	protected issuanceEmail: string = '';
	protected issuanceComments: string = '';
	protected showIssuanceForm = false;

	constructor(
		public shipping: ShippingService,
		public format: FormatsService,
		protected http: HttpResponsesService,
		protected documentViewerService: DocumentViewerService,
		private  msg: MessagesService,
		private mask: LoadMaskService,
		private router: Router,
		private issuanceService: IssuanceService,
		private debug: DebugService,
	) {
	}

	initData() {
		this.getChangeHistory();
	}

	protected getChangeHistory() {
		if (!this.currentShipping) return;
		this.http.get(`/certificate-request/${this.currentShipping.id}/history`).subscribe({
			next: (resp: any) => {
				if (this.currentShipping) {
					this.currentShipping.history = resp.dataRecords?.data || [];
				}
			},
			error: (err) => {
				this.debug.error('DocumentViewComponent', 'Error al consultar el historial', err);
			}
		});
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

	onDeleteRequest() {
		this.msg.confirm('¿Eliminar solicitud?', `¿Está seguro de eliminar esta solicitud? Esta acción no se puede deshacer.`)
			.then((result) => {
				if (result.isConfirmed) {
					this.mask.showBlockUI('Eliminando...');
					this.http.delete(`/certificate-request/${this.currentShipping.id}`).subscribe({
						next: (resp: any) => {
							this.mask.hideBlockUI();
							this.msg.toastMessage('Éxito', resp.message || 'Solicitud eliminada correctamente');
							this.onDeleted.emit();
						},
						error: () => {
							this.mask.hideBlockUI();
						}
					});
				}
			});
	}

	onAddFile() {
		this.canAddPaymentFile = false;
		this.canAddFile = true;
		this.document_type = null;
	}
	onAddFilePayment() {
		this.canAddPaymentFile = true;
		this.canAddFile = false;
		this.document_type = FileDocumentTypeEnum.PAYMENT;
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

	/**
	 * Sube el archivo de pago
	 * Se espera que el archivo sea un PDF, JPG, PNG con el archivo de pago
	 */
	onUploadPayment() {
		const fileUpload = this.fileUploadPayment.nativeElement;
		const file = fileUpload.files[0];
		const validExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
		const fileExtension = file.name.split('.').pop().toLowerCase();
		// Check file extension
		if (!validExtensions.includes(fileExtension)) {
			this.fileUploadPayment.nativeElement.value = '';
			this.msg.errorMessage('', `El archivo debe ser un PDF, JPG o PNG. Extensión actual: ${fileExtension}`);
			return;
		}
		// Check file size and type 2000kb = 2000000
		if (file.size > 2000000) { // 2000kb
			this.fileUploadPayment.nativeElement.value = '';
			const size = (file.size / 1024).toFixed(2); // Convert to KB
			this.msg.errorMessage('',`El archivo no debe ser mayor a 2000kb. Tamaño del archivo ${size}kb.`);
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

		if (this.document_type === FileDocumentTypeEnum.PAYMENT) {
			this.formData.append('document_type', FileDocumentTypeEnum.PAYMENT);
		}

		this.mask.showBlockUI("Subiendo archivo...");
		this.http.post(`/certificate-request/${this.currentShipping.id}/files`, this.formData)
			.subscribe({
			next: (resp: any) => {
				this.currentShipping.files.push(resp.dataRecords.data[0]);
				this.mask.hideBlockUI();
				this.msg.toastMessage('Éxito', resp.message);
				this.files = [];
				this.canAddFile = false;
				this.canAddPaymentFile = false;
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
			return file.document_type === FileDocumentTypeEnum.ATTACHED || file.document_type === FileDocumentTypeEnum.PAYMENT;
		});
	}

	protected getFilesZip(): FileManager[] {
		return this.currentShipping.files.filter((file) => {
			return file.document_type === FileDocumentTypeEnum.CERTIFICATE;
		});
	}

	protected isPaymentFile(file: FileManager): boolean {
		return file.document_type === FileDocumentTypeEnum.PAYMENT;
	}

	// ─── Emisión de certificados ──────────────────────────────────────────────

	/**
	 * Verifica si la solicitud puede ser emitida (estado ACCEPTED).
	 */
	protected canIssue(): boolean {
		return this.currentShipping?.request_status === DocumentStatusEnum.ACCEPTED;
	}

	/**
	 * Muestra/oculta el formulario de emisión.
	 */
	protected toggleIssuanceForm(): void {
		this.showIssuanceForm = !this.showIssuanceForm;
	}

	/**
	 * Dispara la emisión del certificado.
	 */
	protected onIssue(): void {
		const requestId = this.currentShipping.id;
		const body: any = {};
		if (this.issuanceEmail) {
			body.email_certificate = this.issuanceEmail;
		}
		if (this.issuanceComments) {
			body.comments = this.issuanceComments;
		}

		this.msg.confirm(
			'¿Desea emitir el certificado?',
			'Esta acción iniciará el proceso de emisión del certificado digital.'
		).then((result) => {
			if (result.isConfirmed) {
				this.issuanceLoading = true;
				this.mask.showBlockUI('Procesando emisión...');
				this.issuanceService.issue(requestId, body).subscribe({
					next: (resp) => {
						this.mask.hideBlockUI();
						this.issuanceLoading = false;
						this.showIssuanceForm = false;
						this.msg.toastMessage('Éxito', resp.message || 'Emisión iniciada correctamente.');
						this.onCheckIssuanceStatus();
					},
					error: (err) => {
						this.mask.hideBlockUI();
						this.issuanceLoading = false;
						this.debug.error('DocumentViewComponent', 'Error al emitir certificado', err);
					}
				});
			}
		});
	}

	/**
	 * Consulta el estado del trámite de emisión.
	 */
	protected onCheckIssuanceStatus(): void {
		const requestId = this.currentShipping.id;
		this.issuanceLoading = true;
		this.issuanceService.getIssuanceStatus(requestId).subscribe({
			next: (status) => {
				this.issuanceStatus = status;
				this.issuanceLoading = false;
			},
			error: (err) => {
				this.issuanceLoading = false;
				this.debug.error('DocumentViewComponent', 'Error al consultar estado de emisión', err);
			}
		});
	}

	/**
	 * Obtiene la metadata de descarga del P12.
	 */
	protected onGetDownloadMeta(): void {
		const requestId = this.currentShipping.id;
		this.issuanceLoading = true;
		this.issuanceService.getDownloadMeta(requestId).subscribe({
			next: (meta) => {
				this.issuanceDownloadMeta = meta;
				this.issuanceLoading = false;
			},
			error: (err) => {
				this.issuanceLoading = false;
				this.debug.error('DocumentViewComponent', 'Error al obtener metadata de descarga', err);
			}
		});
	}

	/**
	 * Descarga el archivo P12 directamente (streaming binario).
	 */
	protected onDownloadP12(): void {
		const requestId = this.currentShipping.id;
		const url = this.issuanceService.getDownloadFileUrl(requestId);
		window.open(url, '_blank');
	}
}
