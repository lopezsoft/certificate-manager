import {
  Controller,
  Delete,
  Get,
  HttpCode,
  HttpStatus,
  Param,
  ParseIntPipe,
  Post,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiBadRequestResponse,
  ApiBody,
  ApiConsumes,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTooManyRequestsResponse,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import { FastifyRequest } from 'fastify';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { FileManagerService } from '@modules/files/file-manager.service';
import { EndpointRateLimit } from '@common/decorators/rate-limit.decorator';
import { EndpointRateLimitGuard } from '@common/guards/endpoint-rate-limit.guard';

@ApiTags('Files')
@ApiBearerAuth()
@ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
@UseGuards(JwtAuthGuard)
@Controller('certificate-request')
export class FileManagerController {
  constructor(private readonly fileManagerService: FileManagerService) { }

  /**
    * GET /api/v1/certificate-request/:certificateRequestId/files
   */
  @Get(':certificateRequestId/files')
  @ApiOperation({ summary: 'Archivos de una solicitud de certificado' })
  @ApiParam({ name: 'certificateRequestId', type: Number })
  @ApiOkResponse({ description: 'Listado de archivos de la solicitud' })
  async getByCertificate(
    @Param('certificateRequestId', ParseIntPipe) id: number,
  ) {
    const data = await this.fileManagerService.getFilesByCertificate(id);
    return { dataRecords: { data } };
  }

  /**
    * POST /api/v1/certificate-request/:certificateRequestId/files
   * Multipart file upload (Fastify handles multipart via @fastify/multipart)
   */
  @Post(':certificateRequestId/files')
  @UseGuards(JwtAuthGuard, EndpointRateLimitGuard)
  @EndpointRateLimit({ max: 20, windowMs: 60_000 })
  @ApiConsumes('multipart/form-data')
  @ApiOperation({ summary: 'Subir archivo a una solicitud' })
  @ApiParam({ name: 'certificateRequestId', type: Number })
  @ApiBody({
    schema: {
      type: 'object',
      properties: {
        file: { type: 'string', format: 'binary' },
        category: { type: 'string' },
        description: { type: 'string' },
      },
      required: ['file'],
    },
  })
  @ApiOkResponse({ description: 'Archivo subido exitosamente' })
  @ApiTooManyRequestsResponse({ description: 'Demasiadas solicitudes' })
  async upload(
    @Param('certificateRequestId', ParseIntPipe) certificateRequestId: number,
    @Req() req: FastifyRequest,
  ) {
    const data = await (req as any).file();

    if (!data) {
      return { success: false, message: 'No se encontró ningún archivo.' };
    }

    const buffer = await data.toBuffer();
    const fields = (req as any).body ?? {};

    const result = await this.fileManagerService.uploadFile(
      certificateRequestId,
      {
        fieldname: data.fieldname,
        originalname: data.filename,
        mimetype: data.mimetype,
        size: buffer.length,
        buffer,
      },
      fields.category,
      fields.description,
    );

    return {
      message: 'Archivo subido exitosamente.',
      dataRecords: { data: result },
    };
  }

  /**
   * DELETE /api/v1/certificate-request/:certificateRequestId/files/:fileId
   */
  @Delete(':certificateRequestId/files/:fileId')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Eliminar archivo' })
  @ApiParam({ name: 'certificateRequestId', type: Number })
  @ApiParam({ name: 'fileId', type: Number })
  @ApiOkResponse({ description: 'Archivo eliminado' })
  async destroy(
    @Param('certificateRequestId', ParseIntPipe) certificateRequestId: number,
    @Param('fileId', ParseIntPipe) fileId: number,
  ) {
    await this.fileManagerService.deleteFile(fileId, certificateRequestId);
    return { message: 'Archivo eliminado exitosamente.' };
  }
}
