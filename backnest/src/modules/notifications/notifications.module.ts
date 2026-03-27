import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { Notification } from '@database/entities/notification.entity';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { NotificationsController } from './notifications.controller';
import { NotificationsService } from './notifications.service';
import { CertificateNotificationsListener } from './listeners/certificate-notifications.listener';

@Module({
  imports: [TypeOrmModule.forFeature([Notification, CertificateRequest])],
  controllers: [NotificationsController],
  providers: [NotificationsService, CertificateNotificationsListener],
  exports: [NotificationsService],
})
export class NotificationsModule { }
