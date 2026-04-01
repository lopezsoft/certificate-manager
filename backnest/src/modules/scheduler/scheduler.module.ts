import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { CertificateSchedulerService } from './certificate-scheduler.service';

@Module({
  imports: [TypeOrmModule.forFeature([CertificateRequest])],
  providers: [CertificateSchedulerService],
})
export class SchedulerModule { }
