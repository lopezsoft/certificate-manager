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
import { IssuanceService } from "../../services/issuance.service";
import { IssuanceStatus, IssuanceDownloadMeta, ViafirmaStatus, RedownloadResult } from "../../interfaces/issuance.interface";
import { DebugService } from "../../utils/debug.service";
import TokenService from "../../utils/token.service";
import { Subject, interval, Subscription } from "rxjs";
import { takeUntil, switchMap } from "rxjs/operators";
import { ViafirmaInternalStateEnum, ViafirmaInternalStateDescription } from "../../common/enums/ViafirmaInternalState";

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
    ],
    standalone: false
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
	protected readonly ViafirmaInternalStateEnum = ViafirmaInternalStateEnum;
	protected readonly viafirmaInternalStateDescription = ViafirmaInternalStateDescription;
	protected canRejectRequest: boolean = false;
	protected canAddFile: boolean;
	protected files = [];
	protected formData: FormData;

	/** Emisión de certificados */
	protected issuanceStatus: IssuanceStatus | null = null;
	protected issuanceDownloadMeta: IssuanceDownloadMeta | null = null;
	protected issuanceLoading = false;
	protected issuanceEmail: string = '';
	protected issuanceComments: string = '';
	protected showIssuanceForm = false;

	/** Re-descarga Viafirma (solo Admin) */
	protected viafirmaStatus: ViafirmaStatus | null = null;
	protected isRedownloading = false;
	protected redownloadResult: RedownloadResult | null = null;
	protected showRedownloadPinModal = false;
	protected pinCopied = false;

	/** Revocación */
	protected showRevokeModal = false;
	protected revokeLoading = false;
	protected revocationCode = '';
	protected revocationReason = 0;
	protected readonly revocationReasons = [
		{ id: 0, label: 'Sin especificar' },
		{ id: 1, label: 'Clave comprometida' },
		{ id: 2, label: 'Autoridad de certificación comprometida' },
		{ id: 3, label: 'Ha cambiado la afiliación' },
		{ id: 4, label: 'Sustitución' },
		{ id: 5, label: 'Cese de operaciones' },
		{ id: 9, label: 'Permisos retirados' },
		{ id: 10, label: 'AA comprometida' },
	];

	/** Polling Viafirma */
	private readonly viafirmaTerminalStates = [
		ViafirmaInternalStateEnum.ASSEMBLED,
		ViafirmaInternalStateEnum.COMPLETED,
		ViafirmaInternalStateEnum.FAILED,
		ViafirmaInternalStateEnum.FAILED_RECOVERABLE,
		ViafirmaInternalStateEnum.EXPIRED
	];
	private viafirmaPolling$: Subscription | null = null;
	private destroy$ = new Subject<void>();
	protected readonly isAdmin: boolean;
	constructor(
		public shipping: ShippingService,
		public format: FormatsService,
		protected http: HttpResponsesService,
		protected documentViewerService: DocumentViewerService,
		private  msg: MessagesService,
		private mask: LoadMaskService,
		private issuanceService: IssuanceService,
		private debug: DebugService,
		private tokenService: TokenService,
	) {
		this.isAdmin = this.tokenService.isAdmin();
	}

	initData() {
		this.viafirmaStatus = null;
		this.redownloadResult = null;
		this.showRedownloadPinModal = false;
		this.issuanceStatus = null;
		this.issuanceDownloadMeta = null;
		this.stopViafirmaPolling();
		const curr = this.currentShipping;
		if (curr && (curr.request_status === this.DocumentStatusEnum.PROCESSING || curr.request_status === this.DocumentStatusEnum.PROCESSED)) {
			this.onCheckIssuanceStatus();
		}
	}

	public get currentShipping(): CertificateRequest {
		return this.shipping.currentRequestAll;
	}

	protected sendEmail(status: DocumentStatusEnum) {
		this.mask.showBlockUI("Cambiando estado del documento...");
		this.http.post(`/certificate-request/${this.currentShipping.id}/issue`, {
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
			return file.document_type === FileDocumentTypeEnum.ATTACHED || file.document_type === FileDocumentTypeEnum.PAYMENT;
		});
	}

	protected getFilesZip(): FileManager[] {
		return this.currentShipping.files.filter((file) => {
			return file.document_type === FileDocumentTypeEnum.CERTIFICATE;
		});
	}

	// ─── Emisión y Polling ───────────────────────────────────────────────────

	protected onCheckIssuanceStatus(): void {
		const requestId = this.currentShipping?.id;
		if (!requestId) return;
		this.issuanceLoading = true;
		this.issuanceService.getIssuanceStatus(requestId).subscribe({
			next: (status) => {
				this.issuanceStatus = status;
				this.issuanceLoading = false;
				if (status.provider === 'viafirma' && status.data) {
					this.viafirmaStatus = status.data;
					if (!this.viafirmaTerminalStates.includes(this.viafirmaStatus.internal_state)) {
						this.startViafirmaPolling();
					} else {
						this.stopViafirmaPolling();
					}
				}
			},
			error: (err) => {
				this.issuanceLoading = false;
				this.debug.error('RequestInProcessView', 'Error al consultar estado de emisión', err);
			}
		});
	}

	private startViafirmaPolling(): void {
		if (this.viafirmaPolling$) return;
		this.viafirmaPolling$ = interval(30000).pipe(
			takeUntil(this.destroy$),
			switchMap(() => this.issuanceService.getIssuanceStatus(this.currentShipping.id))
		).subscribe({
			next: (status) => {
				this.issuanceStatus = status;
				if (status.provider === 'viafirma' && status.data) {
					this.viafirmaStatus = status.data;
					if (this.viafirmaTerminalStates.includes(this.viafirmaStatus.internal_state)) {
						this.stopViafirmaPolling();
					}
				}
			},
			error: (err) => {
				this.debug.error('RequestInProcessView', 'Error en polling Viafirma', err);
				this.stopViafirmaPolling();
			}
		});
	}

	private stopViafirmaPolling(): void {
		this.viafirmaPolling$?.unsubscribe();
		this.viafirmaPolling$ = null;
	}

	protected onDownloadP12(): void {
		const requestId = this.currentShipping.uuid;
		this.mask.showBlockUI('Preparando descarga...');
		this.issuanceService.getDownloadFileUrl(requestId).subscribe({
			next: (meta) => {
				this.mask.hideBlockUI();
				window.open(meta.download_url, '_blank');
			},
			error: () => this.mask.hideBlockUI()
		});
	}

	// ─── Re-descarga Viafirma ────────────────────────────────────────────────

	protected get canShowRedownload(): boolean {
		const visibleStates = [
			ViafirmaInternalStateEnum.ASSEMBLED,
			ViafirmaInternalStateEnum.COMPLETED,
			ViafirmaInternalStateEnum.FAILED,
			ViafirmaInternalStateEnum.FAILED_RECOVERABLE,
			ViafirmaInternalStateEnum.DOWNLOADED
		];
		return visibleStates.includes(this.viafirmaStatus?.internal_state.toUpperCase() as ViafirmaInternalStateEnum);
	}

	protected get canRedownload(): boolean {
		const allowedStates = [
			ViafirmaInternalStateEnum.ASSEMBLED,
			ViafirmaInternalStateEnum.COMPLETED,
			ViafirmaInternalStateEnum.DOWNLOADED,
			ViafirmaInternalStateEnum.FAILED_RECOVERABLE
		];
		return allowedStates.includes(this.viafirmaStatus?.internal_state.toUpperCase() as ViafirmaInternalStateEnum);
	}

	protected onRedownload(): void {
		this.msg.confirm(
			'Re-descargar Certificado',
			'<strong>Esta acción:</strong><ul style="text-align:left;margin:8px 0"><li>Consultará el estado actual en Viafirma</li><li>Descargará nuevamente el archivo del certificado</li><li>Generará un <strong>NUEVO PIN</strong> de acceso</li></ul><span class="text-warning"><i class="fas fa-exclamation-triangle"></i> El PIN anterior quedará inválido.</span>',
		).then((result) => {
			if (!result.isConfirmed) return;
			this.isRedownloading = true;
			this.mask.showBlockUI('Re-descargando certificado...');
			this.issuanceService.redownloadCertificate(this.currentShipping.id).subscribe({
				next: (redownload) => {
					this.mask.hideBlockUI();
					this.isRedownloading = false;
					this.redownloadResult = redownload;
					this.showRedownloadPinModal = true;
					this.pinCopied = false;
					this.onCheckIssuanceStatus();
				},
				error: (err) => {
					this.mask.hideBlockUI();
					this.isRedownloading = false;
					this.msg.errorMessage('Error', 'No se pudo re-descargar el certificado.');
				}
			});
		});
	}

	protected copyPin(): void {
		if (!this.redownloadResult?.pin) return;
		if (navigator?.clipboard?.writeText) {
			navigator.clipboard.writeText(this.redownloadResult.pin).then(() => {
				this.pinCopied = true;
				this.msg.toastMessage('Éxito', 'PIN copiado');
				setTimeout(() => this.pinCopied = false, 3000);
			}).catch(() => this.copyPinFallback());
		} else {
			this.copyPinFallback();
		}
	}

	private copyPinFallback(): void {
		try {
			const textarea = document.createElement('textarea');
			textarea.value = this.redownloadResult.pin;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild(textarea);
			textarea.select();
			const success = document.execCommand('copy');
			document.body.removeChild(textarea);
			if (success) {
				this.pinCopied = true;
				this.msg.toastMessage('Éxito', 'PIN copiado');
				setTimeout(() => this.pinCopied = false, 3000);
			}
		} catch (err) {}
	}

	protected closePinModal(): void {
		this.showRedownloadPinModal = false;
		this.redownloadResult = null;
	}

	// ─── Revocación ──────────────────────────────────────────────────────────

	protected closeRevokeModal(): void {
		this.showRevokeModal = false;
		this.revocationCode = '';
	}

	protected openRevokeModal(): void {
		this.showRevokeModal = true;
		this.revocationCode = '';
		this.revocationReason = this.revocationReasons.find(r => r.id === 5).id;
	}

	protected onRevokeSubmit(): void {
		this.msg.confirm(
			'¿Revocar Certificado?',
			'<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta acción es irreversible.</span><br>El certificado dejará de ser válido inmediatamente.'
		).then((result) => {
			if (!result.isConfirmed) return;
			this.revokeLoading = true;
			this.mask.showBlockUI('Revocando certificado...');
			const revocationData = {
				revoking_code: this.revocationCode,
				revocation_reason: this.revocationReason
			};
			this.issuanceService.revokeCertificate(this.currentShipping.uuid, revocationData).subscribe({
				next: () => {
					this.mask.hideBlockUI();
					this.revokeLoading = false;
					this.showRevokeModal = false;
					this.msg.toastMessage('Éxito', 'Certificado revocado exitosamente');
					this.currentShipping.request_status = this.DocumentStatusEnum.REVOKED;
				},
				error: () => {
					this.mask.hideBlockUI();
					this.revokeLoading = false;
				}
			});
		});
	}

	ngOnDestroy(): void {
		this.destroy$.next();
		this.destroy$.complete();
		this.stopViafirmaPolling();
	}
}
