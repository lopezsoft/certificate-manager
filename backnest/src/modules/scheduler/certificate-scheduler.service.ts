import { Injectable } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { InjectRepository } from '@nestjs/typeorm';
import { ConfigService } from '@nestjs/config';
import { Between, LessThanOrEqual, Not, Repository } from 'typeorm';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';

@Injectable()
export class CertificateSchedulerService {
  private readonly CONTEXT = 'CertificateSchedulerService';

  constructor(
    @InjectRepository(CertificateRequest)
    private readonly certRepo: Repository<CertificateRequest>,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) { }

  /**
   * Verificación diaria de certificados próximos a vencer.
   * Se ejecuta cada día a las 8:00 AM (hora del servidor).
   */
  @Cron(CronExpression.EVERY_DAY_AT_8AM)
  async checkExpiringCertificates(): Promise<void> {
    this.logger.log('Iniciando verificación de certificados próximos a vencer...', this.CONTEXT);

    const urgencyLevels = this.configService.get<{
      critical: number;
      high: number;
      medium: number;
    }>('certificate.urgencyLevels', { critical: 7, high: 15, medium: 30 });

    const today = new Date();
    const maxDays = urgencyLevels.medium;
    const futureDate = new Date();
    futureDate.setDate(today.getDate() + maxDays);

    const expiringCerts = await this.certRepo
      .createQueryBuilder('cr')
      .where('cr.expiration_date IS NOT NULL')
      .andWhere('cr.expiration_date BETWEEN :today AND :futureDate', {
        today: today.toISOString(),
        futureDate: futureDate.toISOString(),
      })
      .andWhere('cr.request_status NOT IN (:...excludedStatuses)', {
        excludedStatuses: ['CANCELLED', 'REJECTED'],
      })
      .leftJoinAndSelect('cr.company', 'company')
      .getMany();

    this.logger.log(
      `Certificados próximos a vencer encontrados: ${expiringCerts.length}`,
      this.CONTEXT,
    );

    for (const cert of expiringCerts) {
      const daysRemaining = Math.ceil(
        (cert.expirationDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
      );

      let urgency = 'medium';
      if (daysRemaining <= urgencyLevels.critical) urgency = 'critical';
      else if (daysRemaining <= urgencyLevels.high) urgency = 'high';

      this.logger.log(
        `Certificado #${cert.id} | Empresa: ${cert.companyName} | Días restantes: ${daysRemaining} | Urgencia: ${urgency}`,
        this.CONTEXT,
      );

      // TODO: disparar evento para notificación + email
      // this.eventEmitter.emit('certificate.expiring', { cert, daysRemaining, urgency });
    }

    this.logger.log('Verificación de vencimientos completada.', this.CONTEXT);
  }

  /**
   * Reporte mensual de certificados.
   * Se ejecuta el primer día de cada mes a las 7:00 AM.
   */
  @Cron('0 7 1 * *')
  async generateMonthlyReport(): Promise<void> {
    this.logger.log('Generando reporte mensual de certificados...', this.CONTEXT);

    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);

    const count = await this.certRepo
      .createQueryBuilder('cr')
      .where('cr.created_at BETWEEN :start AND :end', {
        start: firstDay.toISOString(),
        end: lastDay.toISOString(),
      })
      .getCount();

    this.logger.log(
      `Reporte mensual: ${count} solicitudes creadas en ${firstDay.toLocaleDateString()} - ${lastDay.toLocaleDateString()}`,
      this.CONTEXT,
    );

    // TODO: emitir evento para generar y enviar PDF/Excel del reporte
  }
}
