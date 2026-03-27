import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CertificateRequest } from '@database/entities/certificate-request.entity';

/**
 * ReportsModule: generación de informes PDF/Excel de solicitudes.
 * La generación real de PDF se delega a un job de Bull Queue.
 */
@Module({
  imports: [TypeOrmModule.forFeature([CertificateRequest])],
})
export class ReportsModule { }
