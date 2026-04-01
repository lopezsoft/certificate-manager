import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  HttpStatus,
  Param,
  ParseIntPipe,
  Post,
  Put,
  Query,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiBody,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiQuery,
  ApiTooManyRequestsResponse,
  ApiTags,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { EndpointRateLimit } from '@common/decorators/rate-limit.decorator';
import { EndpointRateLimitGuard } from '@common/guards/endpoint-rate-limit.guard';
import { PaginationQueryDto } from '@common/dto/pagination-query.dto';
import { User } from '@database/entities/user.entity';
import { CertificatesService } from './certificates.service';
import { CreateCertificateRequestDto } from './dto/create-certificate-request.dto';
import {
  UpdateCertificateRequestDto,
  UpdateCertificateStatusDto,
} from './dto/update-certificate-request.dto';

class CertFilterQuery extends PaginationQueryDto {
  request_status?: string;
  start_date?: string;
  end_date?: string;
}

@ApiTags('Certificates')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('certificate-request')
export class CertificatesController {
  constructor(private readonly certificatesService: CertificatesService) { }

  /**
    * GET /api/v1/certificate-request
   * Solicitudes de la empresa del usuario autenticado
   */
  @Get()
  @ApiOperation({ summary: 'Listar solicitudes de certificado (paginado)' })
  @ApiQuery({ name: 'page', required: false, type: Number })
  @ApiQuery({ name: 'limit', required: false, type: Number })
  @ApiQuery({ name: 'query', required: false, type: String })
  @ApiQuery({ name: 'request_status', required: false, type: String })
  @ApiQuery({ name: 'start_date', required: false, type: String })
  @ApiQuery({ name: 'end_date', required: false, type: String })
  @ApiOkResponse({ description: 'Listado paginado de solicitudes' })
  async index(@Query() query: CertFilterQuery, @CurrentUser() user: User) {
    const companyId = (user as any).companyId;
    return this.certificatesService.findAll(companyId, query);
  }

  /**
    * GET /api/v1/certificate-request/all
   * Todas las solicitudes (admin)
   */
  @Get('all')
  @ApiOperation({ summary: 'Listar todas las solicitudes (admin)' })
  @ApiQuery({ name: 'page', required: false, type: Number })
  @ApiQuery({ name: 'limit', required: false, type: Number })
  @ApiOkResponse({ description: 'Listado global paginado de solicitudes' })
  async all(@Query() query: CertFilterQuery) {
    return this.certificatesService.findAllGlobal(query);
  }

  /**
    * GET /api/v1/certificate-request/:id
   */
  @Get(':id')
  @ApiOperation({ summary: 'Obtener solicitud por ID' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Detalle de solicitud' })
  async show(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.certificatesService.findOne(id, companyId);
    return { dataRecords: { data } };
  }

  /**
    * POST /api/v1/certificate-request
   */
  @Post()
  @UseGuards(JwtAuthGuard, EndpointRateLimitGuard)
  @EndpointRateLimit({ max: 10, windowMs: 60_000 })
  @ApiOperation({ summary: 'Crear solicitud de certificado' })
  @ApiBody({ type: CreateCertificateRequestDto })
  @ApiCreatedResponse({ description: 'Solicitud creada' })
  @ApiTooManyRequestsResponse({ description: 'Demasiadas solicitudes' })
  async store(
    @Body() dto: CreateCertificateRequestDto,
    @CurrentUser() user: User,
  ) {
    const data = await this.certificatesService.create(dto, user.id);
    return { message: 'Recurso creado exitosamente.', dataRecords: { data } };
  }

  /**
    * PUT /api/v1/certificate-request/:id
   */
  @Put(':id')
  @ApiOperation({ summary: 'Actualizar solicitud de certificado' })
  @ApiParam({ name: 'id', type: Number })
  @ApiBody({ type: UpdateCertificateRequestDto })
  @ApiOkResponse({ description: 'Solicitud actualizada' })
  async update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateCertificateRequestDto,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.certificatesService.update(id, dto, companyId);
    return { dataRecords: { data } };
  }

  /**
    * PUT /api/v1/certificate-request/:id/status
   */
  @Put(':id/status')
  @ApiOperation({ summary: 'Cambiar estado de la solicitud' })
  @ApiParam({ name: 'id', type: Number })
  @ApiBody({ type: UpdateCertificateStatusDto })
  @ApiOkResponse({ description: 'Estado actualizado' })
  async updateStatus(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateCertificateStatusDto,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.certificatesService.updateStatus(
      id,
      dto,
      user.id,
      companyId,
    );
    return { dataRecords: { data } };
  }

  /**
    * DELETE /api/v1/certificate-request/:id
   */
  @Delete(':id')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Eliminar solicitud de certificado' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Solicitud eliminada' })
  async destroy(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    await this.certificatesService.remove(id, companyId);
    return { message: 'Recurso eliminado exitosamente.' };
  }

  /**
   * POST /api/v1/certificate-request/:id/send-mail
   */
  @Post(':id/send-mail')
  @UseGuards(JwtAuthGuard, EndpointRateLimitGuard)
  @EndpointRateLimit({ max: 10, windowMs: 60_000 })
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Enviar correo asociado a la solicitud' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Correo enviado' })
  @ApiTooManyRequestsResponse({ description: 'Demasiadas solicitudes' })
  async sendMail(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    await this.certificatesService.sendMail(id, companyId);
    return { message: 'Correo enviado exitosamente.' };
  }
}
