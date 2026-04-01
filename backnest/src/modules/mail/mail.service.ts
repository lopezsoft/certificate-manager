import { Injectable } from '@nestjs/common';
import { MailerService } from '@nestjs-modules/mailer';
import { ConfigService } from '@nestjs/config';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';

export interface SendMailOptions {
  to: string | string[];
  subject: string;
  template: string;
  context?: Record<string, any>;
}

@Injectable()
export class MailService {
  private readonly CONTEXT = 'MailService';

  constructor(
    private readonly mailerService: MailerService,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) { }

  async send(options: SendMailOptions): Promise<void> {
    try {
      await this.mailerService.sendMail({
        to: options.to,
        subject: options.subject,
        template: options.template,
        context: options.context ?? {},
      });

      this.logger.log(
        `Email enviado a ${Array.isArray(options.to) ? options.to.join(', ') : options.to} | Asunto: ${options.subject}`,
        this.CONTEXT,
      );
    } catch (err) {
      this.logger.error(
        `Error al enviar email: ${(err as Error).message}`,
        (err as Error).stack,
        this.CONTEXT,
      );
      throw err;
    }
  }

  async sendCertificateCreated(
    to: string,
    data: {
      name: string;
      companyName: string;
      certificateId: number;
      status: string;
    },
  ): Promise<void> {
    await this.send({
      to,
      subject: 'Nueva solicitud de certificado creada',
      template: 'certificate-created',
      context: data,
    });
  }

  async sendCertificateStatusChanged(
    to: string,
    data: {
      name: string;
      companyName: string;
      certificateId: number;
      previousStatus: string;
      newStatus: string;
      comments?: string;
    },
  ): Promise<void> {
    await this.send({
      to,
      subject: `Solicitud de certificado ${data.newStatus}`,
      template: 'certificate-status-changed',
      context: data,
    });
  }

  async sendPasswordReset(
    to: string,
    data: { name: string; resetUrl: string },
  ): Promise<void> {
    await this.send({
      to,
      subject: 'Recuperación de contraseña',
      template: 'password-reset',
      context: data,
    });
  }

  async sendExpirationAlert(
    to: string,
    data: {
      name: string;
      companyName: string;
      certificateId: number;
      expirationDate: string;
      daysRemaining: number;
    },
  ): Promise<void> {
    await this.send({
      to,
      subject: `Alerta: certificado expira en ${data.daysRemaining} días`,
      template: 'certificate-expiration',
      context: data,
    });
  }
}
