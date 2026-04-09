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
  ApiBadRequestResponse,
  ApiBody,
  ApiCreatedResponse,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiQuery,
  ApiTooManyRequestsResponse,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { EndpointRateLimit } from '@common/decorators/rate-limit.decorator';
import { EndpointRateLimitGuard } from '@common/guards/endpoint-rate-limit.guard';
import { PaginationQueryDto } from '@common/dto/pagination-query.dto';
import { User } from '@database/entities/user.entity';
import { CertificatesService } from '@modules/certificates/certificates.service';
import { CreateCertificateRequestDto } from '@modules/certificates/dto/create-certificate-request.dto';
import {
  UpdateCertificateRequestDto,
  UpdateCertificateStatusDto,
} from '@modules/certificates/dto/update-certificate-request.dto';

class CertFilterQuery extends PaginationQueryDto {
  request_status?: string;
  start_date?: string;
  end_date?: string;
}

@ApiTags('Certificates')
@ApiBearerAuth()
@ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
@UseGuards(JwtAuthGuard)
@Controller('certificate-request')
export class CertificatesController {
  constructor(private readonly certificatesService: CertificatesService) { }

  /**
    * GET /api/v1/certificate-request
   * Solicitudes de la empresa del usuario autenticado
   */
  @Get()
  @ApiOperation({ summary: 'Listar solicitudes de certificado (paginado)', description: 'Retorna las solicitudes de certificado de la empresa del usuario autenticado con filtros opcionales.' })
  @ApiQuery({ name: 'page', required: false, type: Number, description: 'Número de página' })
  @ApiQuery({ name: 'limit', required: false, type: Number, description: 'Registros por página' })
  @ApiQuery({ name: 'query', required: false, type: String, description: 'Texto de búsqueda libre' })
  @ApiQuery({ name: 'request_status', required: false, type: String, description: 'Filtrar por estado de la solicitud' })
  @ApiQuery({ name: 'start_date', required: false, type: String, description: 'Fecha inicio (YYYY-MM-DD)' })
  @ApiQuery({ name: 'end_date', required: false, type: String, description: 'Fecha fin (YYYY-MM-DD)' })
  @ApiOkResponse({ description: 'Listado paginado de solicitudes' })
  async index(@Query() query: CertFilterQuery, @CurrentUser() user: User) {
    const companyId = user.companyId;
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
  @ApiOperation({ summary: 'Obtener solicitud por ID', description: 'Retorna el detalle completo de una solicitud de certificado.' })
  @ApiParam({ name: 'id', type: Number, description: 'ID de la solicitud' })
  @ApiOkResponse({ description: 'Detalle de solicitud' })
  @ApiNotFoundResponse({ description: 'Solicitud no encontrada' })
  async show(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = user.companyId;
    const data = await this.certificatesService.findOne(id, companyId);
    return { dataRecords: { data } };
  }

  /**
    * POST /api/v1/certificate-request
   */
  @Post()
  @UseGuards(JwtAuthGuard, EndpointRateLimitGuard)
  @EndpointRateLimit({ max: 10, windowMs: 60_000 })
  @ApiOperation({ summary: 'Crear solicitud de certificado', description: 'Crea una nueva solicitud de certificado digital para la empresa del usuario autenticado.' })
  @ApiBody({ type: CreateCertificateRequestDto })
  @ApiCreatedResponse({ description: 'Solicitud creada exitosamente' })
  @ApiBadRequestResponse({ description: 'Datos de entrada inválidos' })
  @ApiTooManyRequestsResponse({ description: 'Demasiadas solicitudes — máx. 10 por minuto' })
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
  @ApiOperation({ summary: 'Actualizar solicitud de certificado', description: 'Actualiza los datos de una solicitud de certificado existente.' })
  @ApiParam({ name: 'id', type: Number, description: 'ID de la solicitud' })
  @ApiBody({ type: UpdateCertificateRequestDto })
  @ApiOkResponse({ description: 'Solicitud actualizada' })
  @ApiBadRequestResponse({ description: 'Datos de entrada inválidos' })
  @ApiNotFoundResponse({ description: 'Solicitud no encontrada' })
  async update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateCertificateRequestDto,
    @CurrentUser() user: User,
  ) {
    const companyId = user.companyId;
    const data = await this.certificatesService.update(id, dto, companyId);
    return { dataRecords: { data } };
  }

  /**
    * PUT /api/v1/certificate-request/:id/status
   */
  @Put(':id/status')
  @ApiOperation({ summary: 'Cambiar estado de la solicitud', description: 'Actualiza el estado de una solicitud de certificado (aprobada, rechazada, etc.).' })
  @ApiParam({ name: 'id', type: Number, description: 'ID de la solicitud' })
  @ApiBody({ type: UpdateCertificateStatusDto })
  @ApiOkResponse({ description: 'Estado actualizado' })
  @ApiBadRequestResponse({ description: 'Estado inválido' })
  @ApiNotFoundResponse({ description: 'Solicitud no encontrada' })
  async updateStatus(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateCertificateStatusDto,
    @CurrentUser() user: User,
  ) {
    const companyId = user.companyId;
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
  @ApiOperation({ summary: 'Eliminar solicitud de certificado', description: 'Elimina permanentemente una solicitud de certificado.' })
  @ApiParam({ name: 'id', type: Number, description: 'ID de la solicitud' })
  @ApiOkResponse({ description: 'Solicitud eliminada' })
  @ApiNotFoundResponse({ description: 'Solicitud no encontrada' })
  async destroy(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = user.companyId;
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
    const companyId = user.companyId;
    await this.certificatesService.sendMail(id, companyId);
    return { message: 'Correo enviado exitosamente.' };
  }
}
