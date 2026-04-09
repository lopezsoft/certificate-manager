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
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';

const ALLOWED_MIME_TYPES = [
  'image/jpeg',
  'image/png',
  'image/gif',
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/zip',
  'application/x-zip-compressed',
];

const MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB
const MAX_FILES_PER_REQUEST = 6;

/**
 * Servicio de gestión de archivos.
 * Replica el patrón de storage de Laravel:
 *   disco "attachment" → storage/app/attachments/
 *   estructura: companies/{companyId}/{year}/{month}/{dni}{dv}/
 *   file_path en BD: companies/1/2025/04/100092351/archivo.pdf  (relativo al disco)
 *   URL pública:     {APP_URL}/attachments/{file_path}
 */
@Injectable()
export class FileManagerService {
  private readonly CONTEXT = 'FileManagerService';
  /** Raíz absoluta del disco "attachment" (equivale a storage_path('app/attachments')) */
  private readonly attachmentRoot: string;

  constructor(
    @InjectRepository(FileManager)
    private readonly fileManagerRepo: Repository<FileManager>,
    @InjectRepository(CertificateRequest)
    private readonly certRequestRepo: Repository<CertificateRequest>,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) {
    const storagePath = this.configService.get<string>(
      'app.storagePath',
      path.join(process.cwd(), 'storage', 'app'),
    );
    this.attachmentRoot = path.join(storagePath, 'attachments');

    // Garantizar que el directorio raíz exista
    if (!fs.existsSync(this.attachmentRoot)) {
      fs.mkdirSync(this.attachmentRoot, { recursive: true });
    }
  }

  // ── Queries ──────────────────────────────────────────────────────────────────

  async getFilesByCertificate(
    certificateRequestId: number,
  ): Promise<FileManager[]> {
    return this.fileManagerRepo.find({
      where: { certificateRequestId, isActive: true },
    });
  }

  // ── Upload ───────────────────────────────────────────────────────────────────

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
    // ── Validaciones ──
    if (!ALLOWED_MIME_TYPES.includes(file.mimetype)) {
      throw new BadRequestException(
        'Tipo de archivo no permitido. Solo se aceptan imágenes, PDF, documentos Word y archivos ZIP.',
      );
    }

    if (file.size > MAX_FILE_SIZE_BYTES) {
      throw new BadRequestException(
        'El archivo supera el tamaño máximo permitido de 2 MB.',
      );
    }

    const existingCount = await this.fileManagerRepo.count({
      where: { certificateRequestId },
    });
    if (existingCount >= MAX_FILES_PER_REQUEST) {
      throw new BadRequestException(
        `No se pueden subir más de ${MAX_FILES_PER_REQUEST} archivos por solicitud.`,
      );
    }

    // ── Obtener o construir basePath (patrón Laravel) ──
    const certRequest = await this.certRequestRepo.findOne({
      where: { id: certificateRequestId },
    });
    if (!certRequest) {
      throw new NotFoundException('Solicitud de certificado no encontrada.');
    }

    let basePath = certRequest.basePath;
    if (!basePath) {
      basePath = this.buildFolderName(
        certRequest.companyId,
        certRequest.dni,
        certRequest.dv,
      );
      await this.certRequestRepo.update(certificateRequestId, { basePath });
    }

    // ── Crear directorio y escribir archivo ──
    const absoluteDir = path.join(this.attachmentRoot, basePath);
    if (!fs.existsSync(absoluteDir)) {
      fs.mkdirSync(absoluteDir, { recursive: true });
    }

    const fileName = file.originalname;
    const absolutePath = path.join(absoluteDir, fileName);
    fs.writeFileSync(absolutePath, file.buffer);

    // ── filePath relativo al disco attachment (igual que Laravel) ──
    // Ejemplo: companies/1/2025/04/100092351/documento.pdf
    const relativePath = path.posix.join(basePath, fileName);

    const ext = path.extname(fileName).toLowerCase().replace('.', '');
    const uuid = randomUUID();

    const entity = this.fileManagerRepo.create({
      uuid,
      certificateRequestId,
      filePath: relativePath,
      fileName,
      originalName: file.originalname,
      fileType: file.mimetype,
      fileSize: file.size,
      extension: ext,
      category,
      description,
      isActive: true,
    });

    const saved = await this.fileManagerRepo.save(entity);
    this.logger.log(
      `Archivo subido: ${fileName} → ${relativePath}`,
      this.CONTEXT,
    );

    return saved;
  }

  // ── Delete ───────────────────────────────────────────────────────────────────

  async deleteFile(id: number, certificateRequestId?: number): Promise<void> {
    const whereClause: Record<string, number> = { id };
    if (certificateRequestId) {
      whereClause['certificateRequestId'] = certificateRequestId;
    }

    const file = await this.fileManagerRepo.findOne({ where: whereClause });
    if (!file) {
      throw new NotFoundException('Archivo no encontrado.');
    }

    // Eliminar archivo físico del disco
    const absolutePath = path.join(this.attachmentRoot, file.filePath);
    if (fs.existsSync(absolutePath)) {
      fs.unlinkSync(absolutePath);
      this.logger.log(
        `Archivo físico eliminado: ${absolutePath}`,
        this.CONTEXT,
      );
    }

    // Hard delete (consistente con Laravel que usa $file->delete())
    await this.fileManagerRepo.remove(file);
    this.logger.log(`Registro de archivo eliminado: ${id}`, this.CONTEXT);
  }

  // ── Helpers privados ─────────────────────────────────────────────────────────

  /**
   * Construye la ruta de carpeta igual que Laravel:
   * companies/{companyId}/{year}/{month}/{dni}{dv}
   */
  private buildFolderName(
    companyId: number,
    dni: string,
    dv: number,
  ): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `companies/${companyId}/${year}/${month}/${dni}${dv}`;
  }
}
