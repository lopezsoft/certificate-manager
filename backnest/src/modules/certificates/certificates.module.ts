import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { ChangeHistory } from '@database/entities/change-history.entity';
import { FileManager } from '@database/entities/file-manager.entity';
import { CertificatesController } from './certificates.controller';
import { CertificatesService } from './certificates.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([CertificateRequest, ChangeHistory, FileManager]),
  ],
  controllers: [CertificatesController],
  providers: [CertificatesService],
  exports: [CertificatesService],
})
export class CertificatesModule { }
