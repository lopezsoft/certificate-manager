import { Component, ElementRef, OnDestroy, ViewChild, Output, EventEmitter, inject } from '@angular/core';
import { animate, style, transition, trigger } from "@angular/animations";
import { ShippingService } from "../../services/shipping.service";
import { FormatsService } from "../../services/formats.service";
import { CertificateRequest, FileManager } from "../../interfaces/file-manager.interface";
import { convertBytesToMB } from "../../common/utils/conversion.helper";
import { HttpResponsesService, MessagesService } from "../../utils";
import {
	DocumentStatusComments,
	DocumentStatusDescription,
	DocumentStatusEnum,
	FileDocumentTypeEnum
} from "../../common/enums/DocumentStatus";
import { LoadMaskService } from "../../services/load-mask.service";
import { Router } from "@angular/router";
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
export class DocumentViewComponent implements OnDestroy {
	@ViewChild('fileUploadCc', { static: false }) fileUploadCc: ElementRef;
	@ViewChild('fileUploadPayment', { static: false }) fileUploadPayment: ElementRef;
	@Output() onDeleted = new EventEmitter<void>();
	protected selectedFile: FileManager;
	protected readonly convertBytesToMB = convertBytesToMB;
	protected readonly documentStatusDescription = DocumentStatusDescription;
	protected readonly DocumentStatusEnum = DocumentStatusEnum;
	protected readonly DocumentStatusComments = DocumentStatusComments;
	protected readonly ViafirmaInternalStateEnum = ViafirmaInternalStateEnum;
	protected readonly viafirmaInternalStateDescription = ViafirmaInternalStateDescription;
	protected readonly viafirmaRemoteStatusDescription = ViafirmaRemoteStatusDescription;
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

	/** Re-descarga Viafirma (solo Admin) */
	protected viafirmaStatus: ViafirmaStatus | null = null;
	protected isRedownloading = false;
	protected redownloadResult: RedownloadResult | null = null;
	protected showRedownloadPinModal = false;
	protected pinCopied = false;
	protected showAdminActions = false;
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
	 * (StateMachine::InternalState). DRAFT/CSR_GENERATED no aplican aquí
	 * porque el card solo se muestra desde SUBMITTED en adelante.
	 * Las etiquetas se toman de `viafirmaInternalStateDescription` (misma
	 * fuente que el resto de la vista) para no tener dos vocabularios
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
	 * StateMachine::ACCREDITATION_FAMILY en el backend). El link de MetaMap
	 * está disponible durante toda esta familia, no solo en el valor "accreditation".
	 */
	private readonly accreditationRemoteStatuses = [
		'accreditation',
		'accreditation_check',
		'accreditation_completed',
		'accreditation_verified',
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
		private router: Router,
		private issuanceService: IssuanceService,
		private debug: DebugService,
		private tokenService: TokenService,
	) {
		this.isAdmin = this.tokenService.isAdmin();
	}

	initData() {
		this.getChangeHistory();
		// Reset estado Viafirma al cambiar de solicitud
		this.viafirmaStatus = null;
		this.redownloadResult = null;
		this.showRedownloadPinModal = false;
		this.issuanceStatus = null;
		this.issuanceDownloadMeta = null;
		this.stopViafirmaPolling();
		const curr = this.currentShipping;
		if ((curr.request_status === this.DocumentStatusEnum.PROCESSING
			|| curr.request_status === this.DocumentStatusEnum.PROCESSED)) {
			this.onCheckIssuanceStatus();
		}
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
			this.msg.errorMessage('', `El archivo no debe ser mayor a 1000kb. Tamaño del archivo ${size}kb.`);
		} else {
			this.files = [];
			this.files.push({ data: file, inProgress: false, progress: 0 });
			// Convertir a base64
			this.convertFileToBase64(this.files[0]);
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
			this.msg.errorMessage('', `El archivo no debe ser mayor a 2000kb. Tamaño del archivo ${size}kb.`);
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
			]
		};

		if (this.document_type === FileDocumentTypeEnum.PAYMENT) {
			payload.document_type = FileDocumentTypeEnum.PAYMENT;
		}

		this.mask.showBlockUI("Subiendo archivo...");
		this.http.post(`/certificate-request/${this.currentShipping.id}/files`, payload)
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
	 * Consulta el estado del trámite de emisión unificado.
	 * Actualiza simultáneamente el estado general y el estado específico de Viafirma.
	 */
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
				this.debug.error('DocumentViewComponent', 'Error al consultar estado de emisión', err);
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

	// ─── Re-descarga Viafirma (Admin) ────────────────────────────────────────

	/**
	 * Determina si el estado de Viafirma permite mostrar el botón de re-descarga.
	 * Visible: ASSEMBLED, COMPLETED, FAILED, DOWNLOADED
	 */
	protected get canShowRedownload(): boolean {
		const visibleStates = [
			ViafirmaInternalStateEnum.ASSEMBLED,
			ViafirmaInternalStateEnum.COMPLETED,
			ViafirmaInternalStateEnum.FAILED,
			ViafirmaInternalStateEnum.FAILED_RECOVERABLE,
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
			ViafirmaInternalStateEnum.DOWNLOADED,
			ViafirmaInternalStateEnum.FAILED_RECOVERABLE
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
	 * Clase de badge según el internal_state actual, siguiendo la misma
	 * convención de `getUrgencyClass` en dashboard.component.ts.
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
	 * Visible mientras el remote_status esté dentro de la familia de acreditación
	 * y el backend haya devuelto el link de MetaMap.
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
	 * Descripción legible del sub-estado remoto actual (ver ViafirmaRemoteStatusDescription).
	 * Varios remote_status distintos colapsan al mismo internal_state POLLING; esta
	 * etiqueta es la única forma de explicar qué está pasando realmente durante la espera.
	 * Se oculta durante la familia de acreditación porque el CTA de KYC ya lo explica.
	 */
	protected get remoteStatusLabel(): string | null {
		const remoteStatus = this.viafirmaStatus?.remote_status;
		if (!remoteStatus || this.isInAccreditationFamily) {
			return null;
		}
		return ViafirmaRemoteStatusDescription[remoteStatus] || null;
	}

	/**
	 * Link para reenviar el enlace de acreditación KYC por WhatsApp.
	 * Si la solicitud tiene un celular/teléfono registrado, abre el chat directo
	 * con el mensaje precargado; si no, abre el selector de contacto de WhatsApp.
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

	/**
	 * Información de expiración del trámite (tiempo restante y nivel de urgencia).
	 * Null cuando no aplica: sin fecha de expiración, o el trámite ya llegó a un estado terminal.
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
	 * Determina si el polling está activo (estado no terminal).
	 */
	protected get isPolling(): boolean {
		const pollingStates = [
			ViafirmaInternalStateEnum.SUBMITTED,
			ViafirmaInternalStateEnum.POLLING,
			ViafirmaInternalStateEnum.READY_TO_DOWNLOAD
		];
		return pollingStates.includes(this.viafirmaStatus?.internal_state.toUpperCase() as ViafirmaInternalStateEnum);
	}

	/**
	 * Inicia el polling cada 30 segundos hasta alcanzar un estado terminal.
	 */
	private startViafirmaPolling(): void {
		if (this.viafirmaPolling$) return; // Ya está corriendo
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
				this.debug.error('DocumentViewComponent', 'Error en polling Viafirma', err);
				this.stopViafirmaPolling();
			}
		});
	}

	private stopViafirmaPolling(): void {
		this.viafirmaPolling$?.unsubscribe();
		this.viafirmaPolling$ = null;
	}

	/**
	 * Ejecuta el flujo de re-descarga: confirmación → POST → modal con PIN.
	 */
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
					this.getChangeHistory();
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
					this.debug.error('DocumentViewComponent', 'Error en re-descarga Viafirma', err);
				}
			});
		});
	}

	/**
	 * Copia el PIN al portapapeles usando fallback a textarea si es necesario.
	 */
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
				this.debug.error('DocumentViewComponent', 'Error al copiar con Clipboard API', err);
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
			this.debug.error('DocumentViewComponent', 'Error en fallback de copia', err);
			this.msg.errorMessage('Error', 'No se pudo copiar. Intente copiar manualmente.');
		}
	}

	/**
	 * Cierra el modal de PIN (solo cuando el admin haya confirmado copiarlo).
	 */
	protected closePinModal(): void {
		this.showRedownloadPinModal = false;
		this.redownloadResult = null;
	}

	// ─── Revocación (Frontend UI) ──────────────────────────────────────────────

	protected onRevokeSubmit(): void {
		if (!this.viafirmaStatus.revocation_code || this.viafirmaStatus.revocation_code.trim() === '') {
			this.msg.errorMessage('Error', 'No se puede revocar el certificado: no se encontró un código de revocación válido.');
			return;
		}

		this.msg.confirm(
			'¿Revocar Certificado?',
			'<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta acción es irreversible.</span><br>El certificado dejará de ser válido inmediatamente.'
		).then((result) => {
			if (!result.isConfirmed) return;
			this.revocationCode = this.viafirmaStatus.revocation_code;
			this.revocationReason = this.revocationReasons.find(r => r.id === 5).id; // Default to "Sin especificar" if not set
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
					// Update visual status
					this.currentShipping.request_status = this.DocumentStatusEnum.REVOKED;
					this.getChangeHistory(); // Refrescar historial
				},
				error: () => {
					this.mask.hideBlockUI();
					this.revokeLoading = false;
				}
			});
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
			this.debug.error('DocumentViewComponent', 'Error al convertir archivo a base64');
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
