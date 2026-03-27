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
import { ApiBearerAuth, ApiConsumes, ApiOperation, ApiTags } from '@nestjs/swagger';
import { FastifyRequest } from 'fastify';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { FileManagerService } from './file-manager.service';

@ApiTags('Files')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('certificate-request')
export class FileManagerController {
  constructor(private readonly fileManagerService: FileManagerService) { }

  /**
    * GET /api/v1/certificate-request/:certificateRequestId/files
   */
  @Get(':certificateRequestId/files')
  @ApiOperation({ summary: 'Archivos de una solicitud de certificado' })
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
  @ApiConsumes('multipart/form-data')
  @ApiOperation({ summary: 'Subir archivo a una solicitud' })
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
  async destroy(@Param('fileId', ParseIntPipe) fileId: number) {
    await this.fileManagerService.deleteFile(fileId);
    return { message: 'Archivo eliminado exitosamente.' };
  }
}
