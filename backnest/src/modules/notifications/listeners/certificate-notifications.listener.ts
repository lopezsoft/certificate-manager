import { OnEvent } from '@nestjs/event-emitter';
import { Injectable } from '@nestjs/common';
import { CertificateCreatedEvent } from '@modules/certificates/events/certificate-created.event';
import { CertificateStatusChangedEvent } from '@modules/certificates/events/certificate-status-changed.event';
import { NotificationsService } from '@modules/notifications/notifications.service';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';

@Injectable()
export class CertificateNotificationsListener {
  private readonly CONTEXT = 'CertificateNotificationsListener';

  constructor(
    private readonly notificationsService: NotificationsService,
    private readonly logger: SmartLoggerService,
  ) { }

  @OnEvent('certificate.created')
  async handleCertificateCreated(event: CertificateCreatedEvent): Promise<void> {
    try {
      if (event.requestedBy) {
        await this.notificationsService.create(
          'User',
          event.requestedBy,
          'App\\Notifications\\CertificateCreated',
          {
            certificate_request_id: event.certificateRequestId,
            company_id: event.companyId,
            company_name: event.companyName,
            message: `Se ha creado una nueva solicitud de certificado para ${event.companyName}.`,
          },
        );
      }
    } catch (err) {
      this.logger.error(
        `Error al crear notificación de certificado creado: ${(err as Error).message}`,
        undefined,
        this.CONTEXT,
      );
    }
  }

  @OnEvent('certificate.status.changed')
  async handleStatusChanged(
    event: CertificateStatusChangedEvent,
  ): Promise<void> {
    try {
      if (event.changedByUserId) {
        await this.notificationsService.create(
          'User',
          event.changedByUserId,
          'App\\Notifications\\CertificateStatusChanged',
          {
            certificate_request_id: event.certificateRequestId,
            previous_status: event.previousStatus,
            new_status: event.newStatus,
            comments: event.comments,
            message: `La solicitud ${event.certificateRequestId} cambió de estado: ${event.previousStatus} → ${event.newStatus}.`,
          },
        );
      }
    } catch (err) {
      this.logger.error(
        `Error al crear notificación de cambio de estado: ${(err as Error).message}`,
        undefined,
        this.CONTEXT,
      );
    }
  }
}
