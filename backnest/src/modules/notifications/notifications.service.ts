import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Between, IsNull, Repository } from 'typeorm';
import { Notification } from '@database/entities/notification.entity';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';

@Injectable()
export class NotificationsService {
  private readonly CONTEXT = 'NotificationsService';

  constructor(
    @InjectRepository(Notification)
    private readonly notificationRepo: Repository<Notification>,
    @InjectRepository(CertificateRequest)
    private readonly certificateRepo: Repository<CertificateRequest>,
    private readonly logger: SmartLoggerService,
  ) { }

  async getForUser(userId: number): Promise<Notification[]> {
    return this.notificationRepo.find({
      where: { notifiableType: 'User', notifiableId: userId },
      order: { createdAt: 'DESC' },
    });
  }

  async getUnreadForUser(userId: number): Promise<Notification[]> {
    return this.notificationRepo.find({
      where: {
        notifiableType: 'User',
        notifiableId: userId,
        readAt: IsNull(),
      },
      order: { createdAt: 'DESC' },
    });
  }

  async markAsRead(id: string, userId: number): Promise<Notification | null> {
    const notification = await this.notificationRepo.findOne({
      where: { id, notifiableType: 'User', notifiableId: userId },
    });

    if (!notification) return null;

    notification.readAt = new Date();
    notification.updatedAt = new Date();
    return this.notificationRepo.save(notification);
  }

  async markAllAsRead(userId: number): Promise<void> {
    await this.notificationRepo
      .createQueryBuilder()
      .update()
      .set({ readAt: new Date(), updatedAt: new Date() })
      .where('notifiable_type = :type', { type: 'User' })
      .andWhere('notifiable_id = :id', { id: userId })
      .andWhere('read_at IS NULL')
      .execute();

    this.logger.log(
      `Notificaciones marcadas como leídas para usuario: ${userId}`,
      this.CONTEXT,
    );
  }

  async create(
    notifiableType: string,
    notifiableId: number,
    type: string,
    data: Record<string, any>,
  ): Promise<Notification> {
    const notification = this.notificationRepo.create({
      type,
      notifiableType,
      notifiableId,
      data,
    });

    return this.notificationRepo.save(notification);
  }

  async getExpiringCertificates(days = 30): Promise<
    Array<
      CertificateRequest & {
        daysRemaining: number;
        urgency: 'critical' | 'high' | 'medium';
      }
    >
  > {
    const now = new Date();
    const end = new Date();
    end.setDate(end.getDate() + days);

    const items = await this.certificateRepo.find({
      where: {
        expirationDate: Between(now, end),
      },
      order: { expirationDate: 'ASC' },
    });

    return items.map((item) => {
      const diffMs = item.expirationDate.getTime() - now.getTime();
      const daysRemaining = Math.max(0, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));

      let urgency: 'critical' | 'high' | 'medium' = 'medium';
      if (daysRemaining <= 7) urgency = 'critical';
      else if (daysRemaining <= 15) urgency = 'high';

      return Object.assign(item, { daysRemaining, urgency });
    });
  }

  async triggerExpiringNotificationsNow(): Promise<{ total: number }> {
    const expiring = await this.getExpiringCertificates(30);
    this.logger.log(
      `Trigger manual de notificaciones de vencimiento: ${expiring.length} certificados`,
      this.CONTEXT,
    );
    return { total: expiring.length };
  }
}
