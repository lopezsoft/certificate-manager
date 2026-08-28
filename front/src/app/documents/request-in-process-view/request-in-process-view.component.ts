import { Component, ElementRef, inject, ViewChild } from '@angular/core';
import { animate, style, transition, trigger } from "@angular/animations";
import { CertificateRequest, FileManager } from "../../interfaces/file-manager.interface";
import { ShippingService } from "../../services/shipping.service";
import { FormatsService } from "../../services/formats.service";
import { HttpResponsesService, MessagesService } from "../../utils";
import {
	DocumentStatusComments,
	DocumentStatusDescription,
	DocumentStatusEnum,
	FileDocumentTypeEnum
} from "../../common/enums/DocumentStatus";
import { convertBytesToMB } from "../../common/utils/conversion.helper";
import { LoadMaskService } from "../../services/load-mask.service";
import { jqxEditorComponent } from 'jqwidgets-ng/jqxeditor';
import { DocumentViewerService } from "../../services/document-viewer.service";
import { IssuanceService } from "../../services/issuance.service";
import { IssuanceStatus, IssuanceDownloadMeta, ViafirmaStatus, RedownloadResult } from "../../interfaces/issuance.interface";
import { DebugService } from "../../utils/debug.service";
import TokenService from "../../utils/token.service";
import { Subject, interval, Subscription } from "rxjs";
import { takeUntil, switchMap } from "rxjs/operators";
import { ViafirmaInternalStateEnum, ViafirmaInternalStateDescription, ViafirmaRemoteStatusDescription } from "../../common/enums/ViafirmaInternalState";
import { IssuanceProviderService } from 'app/services/issuance-provider.service';

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
	@ViewChild('fileUploadZip', { static: false }) fileUploadZip: ElementRef;
	pin: string;
	protected selectedFile: FileManager;
	protected readonly convertBytesToMB = convertBytesToMB;
	protected comments: string = null;
	protected readonly documentStatusDescription = DocumentStatusDescription;
	protected readonly DocumentStatusEnum = DocumentStatusEnum;
	protected readonly ViafirmaInternalStateEnum = ViafirmaInternalStateEnum;
	protected readonly viafirmaInternalStateDescription = ViafirmaInternalStateDescription;
	protected readonly viafirmaRemoteStatusDescription = ViafirmaRemoteStatusDescription;
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
	protected kycLinkCopied = false;

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

	/** Estados de fallo (excluye ASSEMBLED/COMPLETED, que son terminales pero exitosos) */
	private readonly viafirmaFailureStates = [
		ViafirmaInternalStateEnum.FAILED,
		ViafirmaInternalStateEnum.FAILED_RECOVERABLE,
		ViafirmaInternalStateEnum.EXPIRED
	];

	/**
	 * Pasos visibles del trámite, en el orden real de la FSM del backend
	 * (StateMachine::InternalState). Las etiquetas se toman de
	 * `viafirmaInternalStateDescription` para no tener dos vocabularios
	 * distintos describiendo el mismo estado.
	 */
	protected readonly issuanceSteps: ViafirmaInternalStateEnum[] = [
		ViafirmaInternalStateEnum.SUBMITTED,
		ViafirmaInternalStateEnum.POLLING,
		ViafirmaInternalStateEnum.READY_TO_DOWNLOAD,
		ViafirmaInternalStateEnum.DOWNLOADED,
		ViafirmaInternalStateEnum.ASSEMBLED,
		ViafirmaInternalStateEnum.COMPLETED,
	];

	/**
	 * Familia de estados remotos de acreditación KYC (misma regla que
	 * StateMachine::ACCREDITATION_FAMILY en el backend).
	 */
	private readonly accreditationRemoteStatuses = [
		'accreditation',
		'accreditation_check',
		'accreditation_completed',
		'accreditation_verified',
		'accreditation_rejected'
	];
	private viafirmaPolling$: Subscription | null = null;
	private destroy$ = new Subject<void>();
	protected readonly isAdmin: boolean;

	protected issuanceProvider: IssuanceProviderService = inject(IssuanceProviderService);
	constructor(
		public shipping: ShippingService,
		public format: FormatsService,
		protected http: HttpResponsesService,
		protected documentViewerService: DocumentViewerService,
		private msg: MessagesService,
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
		const requestId = this.currentShipping.uuid;
		const fileUuid = file.uuid;
		this.mask.showBlockUI('Procesando descarga de certificado...');
		this.issuanceService.getDownloadFile(requestId, fileUuid).subscribe({
			next: (meta) => {
				this.mask.hideBlockUI();
				if (file.extension_file === 'pdf') {
					this.documentViewerService.open(meta.download_url, this.currentShipping.company_name + ' - ' + file.file_name);
				} else {
					this.http.openDocument(meta.download_url);
				}
			},
			error: () => {
				this.mask.hideBlockUI();
				this.issuanceLoading = false;
			}
		});
	}

	/**
 * Obtiene la metadata de descarga del P12.
 */
	protected onGetDownloadMeta(): void {
		const requestId = this.currentShipping.uuid;
		this.issuanceLoading = true;
		this.mask.showBlockUI('Procesando descarga de certificado...');
		this.issuanceService.getDownloadFileUrl(requestId).subscribe({
			next: (meta) => {
				this.mask.hideBlockUI();
				window.open(meta.download_url, '_blank');
				this.issuanceLoading = false;
			},
			error: () => {
				this.mask.hideBlockUI();
				this.issuanceLoading = false;
			}
		});
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

	protected isViafirma(): boolean {
		return this.issuanceProvider.isViafirmaByCompany(this.currentShipping.company);
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
			this.msg.errorMessage('', `El archivo no debe ser mayor a 100kb. Tamaño del archivo ${size}kb.`);
		} else if (file.size === 0) {
			this.fileUploadZip.nativeElement.value = '';
			this.msg.errorMessage('', 'El archivo no puede estar vacío');
		} else if (file.type !== 'application/x-zip-compressed') {
			this.fileUploadZip.nativeElement.value = '';
			this.msg.errorMessage('', 'El archivo debe ser un ZIP');
		} else {
			this.files = [];
			this.files.push({ data: file, inProgress: false, progress: 0 });
			// Convertir a base64
			this.convertFileToBase64(this.files[0]);
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

		// Verificar que el archivo tenga base64 convertido
		if (!this.files[0].base64) {
			this.msg.errorMessage('', 'El archivo aún se está procesando. Por favor, intenta de nuevo.');
			return;
		}

		const payload: any = {
			attachments: [
				{
					name: this.files[0].data.name,
					type: this.files[0].data.type,
					size: this.files[0].data.size,
					base64: this.files[0].base64
				}
			],
			pin: this.pin,
			document_type: 'CERTIFICATE'
		};

		this.mask.showBlockUI("Subiendo archivo...");
		this.http.post(`/certificate-request/${this.currentShipping.id}/files`, payload)
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
					this.revocationCode = status.data.revocation_code || '';
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

	/**
	 * Determina si el estado de Viafirma permite mostrar el botón de re-descarga.
	 * Visible: ASSEMBLED, COMPLETED, DOWNLOADED
	 * (FAILED/FAILED_RECOVERABLE excluidos: el backend ya no soporta re-descarga
	 * en esos estados, intentarlo genera error)
	 */
	protected get canShowRedownload(): boolean {
		const visibleStates = [
			ViafirmaInternalStateEnum.ASSEMBLED,
			ViafirmaInternalStateEnum.COMPLETED,
			ViafirmaInternalStateEnum.DOWNLOADED
		];
		return visibleStates.includes(this.viafirmaStatus?.internal_state.toUpperCase() as ViafirmaInternalStateEnum)
			&& !(this.currentShipping.request_status === DocumentStatusEnum.PROCESSED);
	}

	/**
	 * Determina si el botón de re-descarga está habilitado (no disabled).
	 * Habilitado: ASSEMBLED, COMPLETED, DOWNLOADED
	 */
	protected get canRedownload(): boolean {
		const allowedStates = [
			ViafirmaInternalStateEnum.ASSEMBLED,
			ViafirmaInternalStateEnum.COMPLETED,
			ViafirmaInternalStateEnum.DOWNLOADED
		];
		return allowedStates.includes(this.viafirmaStatus?.internal_state.toUpperCase() as ViafirmaInternalStateEnum)
			&& !(this.currentShipping.request_status === DocumentStatusEnum.PROCESSED);
	}

	/**
	 * Índice del paso actual dentro de `issuanceSteps` (-1 si el estado no
	 * pertenece a la línea de progreso, ej. estados de fallo).
	 */
	protected get issuanceStepIndex(): number {
		const state = this.viafirmaStatus?.internal_state?.toUpperCase();
		return this.issuanceSteps.findIndex(step => step === state);
	}

	/**
	 * Determina si el trámite está en un estado de fallo (FAILED, FAILED_RECOVERABLE, EXPIRED).
	 */
	protected get isIssuanceFailed(): boolean {
		return this.viafirmaFailureStates.includes(this.viafirmaStatus?.internal_state?.toUpperCase() as ViafirmaInternalStateEnum);
	}

	/**
	 * Clase de badge según el internal_state actual.
	 */
	protected get issuanceStatusBadgeClass(): string {
		const state = this.viafirmaStatus?.internal_state?.toUpperCase();
		const map: Record<string, string> = {
			[ViafirmaInternalStateEnum.FAILED]: 'bg-danger',
			[ViafirmaInternalStateEnum.EXPIRED]: 'bg-danger',
			[ViafirmaInternalStateEnum.FAILED_RECOVERABLE]: 'bg-warning text-dark',
			[ViafirmaInternalStateEnum.READY_TO_DOWNLOAD]: 'badge-light-success',
			[ViafirmaInternalStateEnum.DOWNLOADED]: 'badge-light-success',
			[ViafirmaInternalStateEnum.ASSEMBLED]: 'badge-light-success',
			[ViafirmaInternalStateEnum.COMPLETED]: 'bg-success',
		};
		return map[state] || 'badge-light-info';
	}

	/**
	 * Indica si debe mostrarse el CTA de verificación de identidad (KYC).
	 */
	protected get showKycAccreditation(): boolean {
		return !!this.viafirmaStatus?.kyc_accreditation_link && this.isInAccreditationFamily;
	}

	private get isInAccreditationFamily(): boolean {
		const remoteStatus = this.viafirmaStatus?.remote_status?.toLowerCase();
		return this.accreditationRemoteStatuses.includes(remoteStatus);
	}

	/**
	 * Detecta si la acreditación KYC fue rechazada (verificación fallida).
	 */
	protected get isAccreditationRejected(): boolean {
		return this.viafirmaStatus?.remote_status?.toLowerCase() === 'accreditation_rejected';
	}

	/**
	 * Detecta si el cliente completó la verificación de identidad en MetaMap.
	 */
	protected get isKycFlowCompleted(): boolean {
		return !!this.viafirmaStatus?.kyc_flow_completed_at;
	}

	/**
	 * Formatea la fecha/hora en que se completó el flujo KYC.
	 */
	protected get kycFlowCompletedLabel(): string {
		if (!this.viafirmaStatus?.kyc_flow_completed_at) return '';
		const date = new Date(this.viafirmaStatus.kyc_flow_completed_at);
		return date.toLocaleString('es-CO', {
			year: 'numeric',
			month: 'long',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
	}

	/**
	 * Descripción legible del sub-estado remoto actual.
	 */
	protected get remoteStatusLabel(): string | null {
		const remoteStatus = this.viafirmaStatus?.remote_status;
		if (!remoteStatus) {
			return null;
		}
		return ViafirmaRemoteStatusDescription[remoteStatus] || null;
	}

	/**
	 * Información de expiración del trámite (tiempo restante y clase de badge).
	 * Null cuando no aplica: sin fecha de expiración, o estado ya terminal.
	 */
	protected get expirationInfo(): { label: string; badgeClass: string } | null {
		const expiresAt = this.viafirmaStatus?.expires_at;
		const state = this.viafirmaStatus?.internal_state?.toUpperCase() as ViafirmaInternalStateEnum;
		if (!expiresAt || this.viafirmaTerminalStates.includes(state)) {
			return null;
		}
		const diffMs = new Date(expiresAt).getTime() - Date.now();
		if (diffMs <= 0) {
			return { label: 'Vencido', badgeClass: 'bg-danger' };
		}
		const totalHours = Math.floor(diffMs / 3_600_000);
		const days = Math.floor(totalHours / 24);
		const hours = totalHours % 24;
		const label = days > 0 ? `Vence en ${days}d ${hours}h` : `Vence en ${totalHours}h`;
		const badgeClass = totalHours < 24 ? 'bg-danger' : totalHours < 48 ? 'bg-warning text-dark' : 'badge-light-secondary';
		return { label, badgeClass };
	}

	/**
	 * Link para reenviar el enlace de acreditación KYC por WhatsApp.
	 */
	protected get kycWhatsappLink(): string {
		const link = this.viafirmaStatus?.kyc_accreditation_link;
		if (!link) return '';
		const message = `Hola, para continuar con la emisión de su certificado digital debe completar su verificación de identidad en el siguiente enlace: ${link}`;
		const phone = this.normalizePhoneForWhatsapp(this.currentShipping?.mobile || this.currentShipping?.phone);
		const base = phone ? `https://wa.me/${phone}` : 'https://wa.me/';
		return `${base}?text=${encodeURIComponent(message)}`;
	}

	/**
	 * Normaliza un número local a formato E.164 sin '+' para wa.me.
	 * Asume indicativo de Colombia (57) cuando el número tiene 10 dígitos sin indicativo.
	 */
	private normalizePhoneForWhatsapp(raw: string | null | undefined): string {
		const digits = (raw || '').replace(/\D/g, '');
		if (!digits) return '';
		return digits.length === 10 ? `57${digits}` : digits;
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
					const status = err?.status;
					const messages: Record<number, string> = {
						403: 'No tiene permisos para realizar esta operación.',
						404: 'No se encontró el trámite de certificado.',
						409: `El certificado aún no está disponible para descarga. Estado: ${err?.error?.remote_status ?? 'desconocido'}`,
						422: 'La llave privada fue purgada. Contacte al soporte para una nueva emisión.',
						502: 'Error de comunicación con Viafirma. Intente nuevamente en unos minutos.',
					};
					this.msg.errorMessage('Error en re-descarga', messages[status] ?? 'Error inesperado. Contacte al soporte técnico.');
					this.debug.error('RequestInProcessViewComponent', 'Error en re-descarga Viafirma', err);
				}
			});
		});
	}

	protected copyPin(): void {
		if (!this.redownloadResult?.pin) return;
		this.copyToClipboard(this.redownloadResult.pin, 'PIN copiado al portapapeles', () => {
			this.pinCopied = true;
			setTimeout(() => this.pinCopied = false, 3000);
		});
	}

	/**
	 * Copia el enlace de acreditación KYC al portapapeles.
	 */
	protected copyKycLink(): void {
		const link = this.viafirmaStatus?.kyc_accreditation_link;
		if (!link) return;
		this.copyToClipboard(link, 'Enlace copiado al portapapeles', () => {
			this.kycLinkCopied = true;
			setTimeout(() => this.kycLinkCopied = false, 3000);
		});
	}

	/**
	 * Copia un texto al portapapeles usando la Clipboard API, con fallback a
	 * textarea para navegadores/contextos sin soporte.
	 */
	private copyToClipboard(text: string, successMessage: string, onCopied: () => void): void {
		if (navigator?.clipboard?.writeText) {
			navigator.clipboard.writeText(text).then(() => {
				onCopied();
				this.msg.toastMessage('Éxito', successMessage);
			}).catch((err) => {
				this.debug.error('RequestInProcessView', 'Error al copiar con Clipboard API', err);
				this.copyToClipboardFallback(text, successMessage, onCopied);
			});
		} else {
			this.copyToClipboardFallback(text, successMessage, onCopied);
		}
	}

	private copyToClipboardFallback(text: string, successMessage: string, onCopied: () => void): void {
		try {
			const textarea = document.createElement('textarea');
			textarea.value = text;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild(textarea);
			textarea.select();
			const success = document.execCommand('copy');
			document.body.removeChild(textarea);
			if (success) {
				onCopied();
				this.msg.toastMessage('Éxito', successMessage);
			} else {
				this.msg.errorMessage('Error', 'No se pudo copiar. Intente copiar manualmente.');
			}
		} catch (err) {
			this.debug.error('RequestInProcessView', 'Error en fallback de copia', err);
			this.msg.errorMessage('Error', 'No se pudo copiar. Intente copiar manualmente.');
		}
	}

	protected closePinModal(): void {
		this.showRedownloadPinModal = false;
		this.redownloadResult = null;
	}

	// ─── Revocación ──────────────────────────────────────────────────────────

	protected closeRevokeModal(): void {
		this.showRevokeModal = false;
	}

	protected openRevokeModal(): void {
		this.showRevokeModal = true;
		this.revocationReason = this.revocationReasons.find(r => r.id === 5).id;
	}

	protected onRevokeSubmit(): void {
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
	}

	/**
	 * Convierte un archivo a base64 y lo agrega a la lista de archivos
	 */
	private convertFileToBase64(fileData: any): void {
		const reader = new FileReader();
		reader.onload = (e) => {
			const base64Data = e.target?.result as string;
			fileData.base64 = base64Data;
		};
		reader.onerror = () => {
			this.debug.error('RequestInProcessViewComponent', 'Error al convertir archivo a base64');
			this.msg.errorMessage('Error', 'No se pudo procesar el archivo. Por favor, intenta de nuevo.');
		};
		reader.readAsDataURL(fileData.data);
	}

	ngOnDestroy(): void {
		this.destroy$.next();
		this.destroy$.complete();
		this.stopViafirmaPolling();
	}
}
