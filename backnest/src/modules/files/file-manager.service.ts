import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { randomUUID } from 'crypto';
import * as path from 'path';
import * as fs from 'fs';
import { FileManager } from '@database/entities/file-manager.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';

const ALLOWED_MIME_TYPES = [
  'image/jpeg',
  'image/png',
  'image/gif',
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

const MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB

@Injectable()
export class FileManagerService {
  private readonly CONTEXT = 'FileManagerService';
  private readonly uploadDir: string;

  constructor(
    @InjectRepository(FileManager)
    private readonly fileManagerRepo: Repository<FileManager>,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) {
    this.uploadDir = path.resolve(
      this.configService.get<string>('app.uploadDir', 'public/attachments'),
    );
  }

  async getFilesByCertificate(
    certificateRequestId: number,
  ): Promise<FileManager[]> {
    return this.fileManagerRepo.find({
      where: { certificateRequestId, isActive: true },
    });
  }

  async uploadFile(
    certificateRequestId: number,
    file: {
      fieldname: string;
      originalname: string;
      mimetype: string;
      size: number;
      buffer: Buffer;
    },
    category?: string,
    description?: string,
  ): Promise<FileManager> {
    if (!ALLOWED_MIME_TYPES.includes(file.mimetype)) {
      throw new BadRequestException(
        'Tipo de archivo no permitido. Solo se aceptan imágenes, PDF y documentos Word.',
      );
    }

    if (file.size > MAX_FILE_SIZE_BYTES) {
      throw new BadRequestException(
        'El archivo supera el tamaño máximo permitido de 2 MB.',
      );
    }

    const ext = path.extname(file.originalname).toLowerCase();
    const uuid = randomUUID();
    const filename = `${uuid}${ext}`;
    const subDir = path.join(
      this.uploadDir,
      String(certificateRequestId),
    );

    if (!fs.existsSync(subDir)) {
      fs.mkdirSync(subDir, { recursive: true });
    }

    const fullPath = path.join(subDir, filename);
    fs.writeFileSync(fullPath, file.buffer);

    const relativePath = path.join(
      'attachments',
      String(certificateRequestId),
      filename,
    );

    const entity = this.fileManagerRepo.create({
      uuid,
      certificateRequestId,
      filePath: relativePath,
      fileName: filename,
      originalName: file.originalname,
      fileType: file.mimetype,
      fileSize: file.size,
      extension: ext.replace('.', ''),
      category,
      description,
      isActive: true,
    });

    const saved = await this.fileManagerRepo.save(entity);
    this.logger.log(
      `Archivo subido: ${filename} para cert ${certificateRequestId}`,
      this.CONTEXT,
    );

    return saved;
  }

  async deleteFile(id: number): Promise<void> {
    const file = await this.fileManagerRepo.findOne({ where: { id } });

    if (!file) {
      throw new NotFoundException('Archivo no encontrado.');
    }

    // Soft delete: marcar como inactivo
    file.isActive = false;
    await this.fileManagerRepo.save(file);

    this.logger.log(`Archivo desactivado: ${id}`, this.CONTEXT);
  }
}
