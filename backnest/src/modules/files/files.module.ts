import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { FileManager } from '@database/entities/file-manager.entity';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { FileManagerController } from '@modules/files/file-manager.controller';
import { FileManagerService } from '@modules/files/file-manager.service';

@Module({
  imports: [TypeOrmModule.forFeature([FileManager, CertificateRequest])],
  controllers: [FileManagerController],
  providers: [FileManagerService],
  exports: [FileManagerService],
})
export class FilesModule { }
