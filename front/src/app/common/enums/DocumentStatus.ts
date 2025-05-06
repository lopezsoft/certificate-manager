/**
 * Estado del documento para las facturas.
 *
 * @enum {string}
 */
export enum DocumentStatusEnum {
	DRAFT = 'DRAFT',
	SENT = 'SENT',
	OVERDUE = 'OVERDUE',
	PAID = 'PAID',
	PARTIALLY_PAID = 'PARTIALLY_PAID',
	CANCELLED = 'CANCELLED',
	REJECTED = 'REJECTED',
	DISPUTED = 'DISPUTED',
	REFUNDED = 'REFUNDED',
	ON_HOLD = 'ON_HOLD',
	DEFINITIVE = 'DEFINITIVE',
	CLOSED = 'CLOSED',
	OPEN = 'OPEN',
	DELETED = 'DELETED',
	PENDING = 'PENDING',
	ANNULLED = 'ANNULLED',
	ACCEPTED = 'ACCEPTED',
	PROCESSING = 'PROCESSING',
	ACCOUNTED = 'ACCOUNTED',
	PROCESSED = 'PROCESSED',
	UNKNOWN = 'UNKNOWN',
	ABIERTO = 'ABIERTO',
	ANULADO = 'ANULADO',
	APROBADO = 'APROBADO',
	CERRADO = 'CERRADO',
	CONCILIADO = 'CONCILIADO',
	CONCILIATION = 'CONCILIATION',
	CONFIRMADO = 'CONFIRMADO',
	ACEPTADO = 'ACEPTADO',
	PROCESANDO = 'PROCESANDO',
	DESCONOCIDO = 'DESCONOCIDO',
}

export const DocumentStatusEnumArray = [
	DocumentStatusEnum.DRAFT,
	DocumentStatusEnum.SENT,
	DocumentStatusEnum.CANCELLED,
	DocumentStatusEnum.REJECTED,
	DocumentStatusEnum.ON_HOLD,
	DocumentStatusEnum.DELETED,
	DocumentStatusEnum.PENDING,
	DocumentStatusEnum.ANNULLED,
	DocumentStatusEnum.ACCEPTED,
	DocumentStatusEnum.PROCESSING,
	DocumentStatusEnum.PROCESSED,
];

export enum UserOfChangeEnum {
	USER = 'USER',
	MANAGER = 'MANAGER',
}

// 'ATTACHED','CERTIFICATE','PAYMENT'
export enum FileDocumentTypeEnum {
	ATTACHED = 'ATTACHED',
	CERTIFICATE = 'CERTIFICATE',
	PAYMENT = 'PAYMENT',
	OTHER = 'OTHER',
}

// Descripción de los estados en español en un array asociativo para su uso en el frontend

export const DocumentStatusDescription = {
	DRAFT: 'Borrador',
	SENT: 'Enviada para revisión',
	OVERDUE: 'Vencido',
	PAID: 'Pagado',
	PARTIALLY_PAID: 'Parcialmente Pagado',
	CANCELLED: 'Cancelada',
	REJECTED: 'Rechazado',
	DISPUTED: 'En disputa',
	REFUNDED: 'Reembolsado',
	ON_HOLD: 'En espera',
	DEFINITIVE: 'Definitivo',
	CLOSED: 'Cerrada',
	OPEN: 'Abierta',
	DELETED: 'Eliminada',
	PENDING: 'Pendiente',
	ANNULLED: 'Anulada',
	ACCEPTED: 'Aceptada',
	PROCESSING: 'En proceso',
	ACCOUNTED: 'Contabilizado',
	PROCESSED: 'ProcesadA',
	UNKNOWN: 'Desconocido',
};

export const DocumentStatusComments = {
	DRAFT: 'La solicitud está en estado de borrador y no ha sido enviada para su revisión.',
	SENT: 'La solicitud a sido enviada para su revisión y aprobación.',
	OVERDUE: 'La solicitud está vencido y requiere atención.',
	PAID: 'La solicitud ha sido pagado.',
	PARTIALLY_PAID: 'La solicitud ha sido parcialmente pagado.',
	CANCELLED: 'La solicitud del certificado ha sido cancelada.',
	REJECTED: 'La solicitud ha sido rechazada por algún error en los documentos.',
	DISPUTED: 'La solicitud está en disputa.',
	REFUNDED: 'El importe del documento ha sido reembolsado.',
	ON_HOLD: 'La solicitud está en espera de acción.',
	DEFINITIVE: 'La solicitud es definitivo y no puede ser modificado.',
	CLOSED: 'La solicitud ha sido cerrado y no se pueden realizar más acciones sobre él.',
	OPEN: 'La solicitud está abierto para su revisión o modificación.',
	DELETED: 'La solicitud ha sido eliminado y no está disponible.',
	PENDING: 'La solicitud está pendiente de acción o revisión.',
	ANNULLED: 'La solicitud ha sido anulado y no tiene validez.',
	ACCEPTED: 'La solicitud ha sido aceptado para ser procesada.',
	PROCESSING: 'La solicitud está siendo procesada.',
	ACCOUNTED: 'La solicitud ha sido contabilizado en el sistema.',
	PROCESSED: 'La solicitud ha sido procesada con éxito.',
	UNKNOWN: 'Estado desconocido del documento.'
}

// Ejemplo de uso:
// const status = DocumentStatusEnum.DRAFT;
// const description = DocumentStatusDescription[status]; // "Borrador"

/**
 * Estado del documento para las facturas.
 *
 * @enum {string}
 */
export enum DocumentTestStatusEnum {
	CREATED = 'CREATED',
	RUNNING = 'RUNNING',
	VALIDATING = 'VALIDATING',
	FINISHED = 'FINISHED',
	ERROR = 'ERROR',
	SENDING = 'SENDING',
	UNKNOWN = 'UNKNOWN',
}
