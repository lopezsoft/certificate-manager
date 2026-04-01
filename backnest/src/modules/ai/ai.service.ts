import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import {
  TextractClient,
  AnalyzeDocumentCommand,
  DetectDocumentTextCommand,
} from '@aws-sdk/client-textract';
import {
  DocumentAnalysisResult,
  PersonType,
} from '@database/entities/document-analysis-result.entity';
import { FileManager } from '@database/entities/file-manager.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import * as fs from 'fs';
import * as path from 'path';

@Injectable()
export class AiService {
  private readonly CONTEXT = 'AiService';
  private textractClient: TextractClient | null = null;

  constructor(
    @InjectRepository(DocumentAnalysisResult)
    private readonly analysisRepo: Repository<DocumentAnalysisResult>,
    @InjectRepository(FileManager)
    private readonly fileRepo: Repository<FileManager>,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) {
    const ocrService = this.configService.get<string>('ai.ocrService', 'none');
    if (ocrService === 'textract') {
      this.textractClient = new TextractClient({
        region: this.configService.get<string>('ai.textract.region', 'us-east-1'),
        credentials: {
          accessKeyId: this.configService.get<string>('ai.textract.accessKeyId', ''),
          secretAccessKey: this.configService.get<string>(
            'ai.textract.secretAccessKey',
            '',
          ),
        },
      });
    }
  }

  async getAnalysisResults(
    certificateRequestId: number,
  ): Promise<DocumentAnalysisResult[]> {
    return this.analysisRepo.find({
      where: { certificateRequestId },
      order: { createdAt: 'DESC' },
    });
  }

  async analyzeDocument(
    fileManagerId: number,
    certificateRequestId: number,
    personType: PersonType = PersonType.NATURAL,
  ): Promise<DocumentAnalysisResult> {
    const file = await this.fileRepo.findOne({
      where: { id: fileManagerId },
    });

    if (!file) {
      throw new NotFoundException('Archivo no encontrado.');
    }

    const ocrService = this.configService.get<string>('ai.ocrService', 'none');

    let analysisResults: Record<string, any> = {};
    let rawText = '';

    if (ocrService === 'textract' && this.textractClient) {
      const result = await this.runTextractAnalysis(file.filePath);
      analysisResults = result.blocks ?? {};
      rawText = result.rawText ?? '';
    } else {
      this.logger.warn(
        'No hay proveedor OCR configurado. Retornando análisis vacío.',
        this.CONTEXT,
      );
    }

    const analysisRecord = this.analysisRepo.create({
      certificateRequestId,
      fileManagerId,
      documentType: file.fileType,
      personType,
      ocrProvider: ocrService,
      analysisResults,
      rawText,
      isProcessed: true,
      hasErrors: false,
      isValid: rawText.length > 0,
    });

    const saved = await this.analysisRepo.save(analysisRecord);
    this.logger.log(
      `Análisis completado para archivo ${fileManagerId}`,
      this.CONTEXT,
    );

    return saved;
  }

  private async runTextractAnalysis(filePath: string): Promise<{
    blocks: Record<string, any>;
    rawText: string;
  }> {
    const fullPath = path.join(process.cwd(), 'public', filePath);

    if (!fs.existsSync(fullPath)) {
      throw new BadRequestException('El archivo no existe en el servidor.');
    }

    const fileBuffer = fs.readFileSync(fullPath);

    try {
      const command = new AnalyzeDocumentCommand({
        Document: { Bytes: fileBuffer },
        FeatureTypes: ['FORMS', 'TABLES'],
      });

      const response = await this.textractClient!.send(command);
      const blocks = response.Blocks ?? [];

      const rawText = blocks
        .filter((b) => b.BlockType === 'LINE')
        .map((b) => b.Text ?? '')
        .join('\n');

      return {
        blocks: { items: blocks },
        rawText,
      };
    } catch (err) {
      this.logger.error(
        `Error en análisis Textract: ${(err as Error).message}`,
        (err as Error).stack,
        this.CONTEXT,
      );
      throw new BadRequestException(
        `Error al analizar el documento: ${(err as Error).message}`,
      );
    }
  }
}
