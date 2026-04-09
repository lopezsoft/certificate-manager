import {
  Body,
  Controller,
  Get,
  Param,
  ParseIntPipe,
  Post,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiBadRequestResponse,
  ApiBody,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import { IsEnum, IsNotEmpty, IsOptional } from 'class-validator';
import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { AiService } from '@modules/ai/ai.service';
import { PersonType } from '@database/entities/document-analysis-result.entity';

class AnalyzeDocumentDto {
  @ApiProperty()
  @IsNotEmpty()
  file_manager_id: number;

  @ApiProperty()
  @IsNotEmpty()
  certificate_request_id: number;

  @ApiPropertyOptional({ enum: PersonType })
  @IsOptional()
  @IsEnum(PersonType)
  person_type?: PersonType;
}

@ApiTags('AI')
@ApiBearerAuth()
@ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
@UseGuards(JwtAuthGuard)
@Controller('ai')
export class AiController {
  constructor(private readonly aiService: AiService) { }

  /**
   * GET /api/v1/ai/results/:certificateRequestId
   */
  @Get('results/:certificateRequestId')
  @ApiOperation({ summary: 'Resultados de análisis de documentos', description: 'Retorna los resultados del análisis OCR/IA para una solicitud de certificado específica.' })
  @ApiParam({ name: 'certificateRequestId', type: Number, description: 'ID de la solicitud de certificado' })
  @ApiOkResponse({ description: 'Resultados del análisis de documentos' })
  @ApiNotFoundResponse({ description: 'Solicitud no encontrada' })
  async results(
    @Param('certificateRequestId', ParseIntPipe) id: number,
  ) {
    const data = await this.aiService.getAnalysisResults(id);
    return { dataRecords: { data } };
  }

  /**
   * POST /api/v1/ai/analyze
   */
  @Post('analyze')
  @ApiOperation({ summary: 'Analizar documento con OCR/IA', description: 'Envía un documento a procesamiento OCR (AWS Textract) y extracción de datos mediante IA.' })
  @ApiBody({ type: AnalyzeDocumentDto })
  @ApiOkResponse({ description: 'Documento analizado exitosamente' })
  @ApiBadRequestResponse({ description: 'Datos de entrada inválidos o archivo no encontrado' })
  async analyze(@Body() dto: AnalyzeDocumentDto) {
    const data = await this.aiService.analyzeDocument(
      dto.file_manager_id,
      dto.certificate_request_id,
      dto.person_type,
    );
    return { dataRecords: { data } };
  }
}
