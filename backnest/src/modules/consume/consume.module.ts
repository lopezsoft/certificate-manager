/**
 * ConsumeModule: endpoints de consulta pública / integración de terceros.
 * Placeholder para Sprint 8 avanzado (consume/external APIs).
 */
import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { ConsumeController } from '@modules/consume/consume.controller';
import { ConsumeService } from '@modules/consume/consume.service';

@Module({
  imports: [TypeOrmModule.forFeature([CertificateRequest])],
  controllers: [ConsumeController],
  providers: [ConsumeService],
})
export class ConsumeModule { }
