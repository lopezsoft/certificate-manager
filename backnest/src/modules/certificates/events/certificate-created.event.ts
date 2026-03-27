/**
 * Evento emitido tras crear una solicitud de certificado.
 * Escuchado por NotificationsListener, MailListener.
 */
export class CertificateCreatedEvent {
  constructor(
    public readonly certificateRequestId: number,
    public readonly companyId: number,
    public readonly companyName: string,
    public readonly requestedBy?: number,
  ) { }
}
