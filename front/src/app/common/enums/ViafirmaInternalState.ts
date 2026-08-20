export enum ViafirmaInternalStateEnum {
  DRAFT = 'DRAFT',
  SUBMITTED = 'SUBMITTED',
  POLLING = 'POLLING',
  READY_TO_DOWNLOAD = 'READY_TO_DOWNLOAD',
  DOWNLOADED = 'DOWNLOADED',
  ASSEMBLED = 'ASSEMBLED',
  COMPLETED = 'COMPLETED',
  FAILED = 'FAILED',
  FAILED_RECOVERABLE = 'FAILED_RECOVERABLE',
  EXPIRED = 'EXPIRED',
}

export const ViafirmaInternalStateDescription: Record<string, string> = {
  DRAFT: 'Borrador',
  SUBMITTED: 'Enviado',
  POLLING: 'Consultando',
  READY_TO_DOWNLOAD: 'Listo para descargar',
  DOWNLOADED: 'Descargado',
  ASSEMBLED: 'Ensamblado',
  COMPLETED: 'Completado',
  FAILED: 'Fallido',
  FAILED_RECOVERABLE: 'Fallo recuperable',
  EXPIRED: 'Expirado',
};

/**
 * Descripción del sub-estado remoto reportado por Viafirma (columna `remote_status`).
 * Complementa a `ViafirmaInternalStateDescription`: varios remote_status distintos
 * colapsan al mismo internal_state (POLLING), así que esta es la única forma de
 * explicarle al usuario qué está pasando realmente durante la espera.
 * Claves tal como las envía la API de Viafirma (ver RemoteStatus.php del backend).
 */
export const ViafirmaRemoteStatusDescription: Record<string, string> = {
  rues_check: 'Validando datos en el RUES',
  accreditation: 'Esperando verificación de identidad (KYC)',
  accreditation_check: 'Validando su verificación de identidad',
  accreditation_completed: 'Verificación de identidad recibida, validando resultado',
  accreditation_verified: 'Verificación de identidad aprobada',
  proposeFor: 'Esperando revisión de un operador de la RA',
  proposedToAcceptance: 'Esperando aceptación de un operador de decisión',
  All_Ok: 'Aprobado, en cola para inscripción en la CA',
  inProcess: 'Firmando el certificado en la CA (máx. 5 min)',
  rues_error: 'Error en validación RUES: requiere intervención del operador',
  accreditation_rejected: 'Verificación de identidad rechazada',
  Generated_Not_Downloaded: 'Certificado generado, listo para descargar',
  signedContract: 'Contrato firmado; el certificado puede descargarse',
  Generated_And_Downloaded: 'Certificado descargado',
  fail: 'El trámite falló de forma irrecuperable',
};
