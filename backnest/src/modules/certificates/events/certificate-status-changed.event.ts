/**
 * Evento emitido cuando cambia el estado de una solicitud.
 */
export class CertificateStatusChangedEvent {
  constructor(
    public readonly certificateRequestId: number,
    public readonly companyId: number,
    public readonly previousStatus: string,
    public readonly newStatus: string,
    public readonly changedByUserId?: number,
    public readonly comments?: string,
  ) { }
}
