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
