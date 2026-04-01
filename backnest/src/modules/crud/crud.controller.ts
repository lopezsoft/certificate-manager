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
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiQuery,
  ApiTags,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { User } from '@database/entities/user.entity';
import { CrudService } from './crud.service';
import { ReportHeader } from '@database/entities/settings/report-header.entity';

@ApiTags('Settings')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller()
export class CrudController {
  constructor(private readonly crudService: CrudService) { }

  /** GET /api/v1/settings */
  @Get('settings')
  @ApiOperation({ summary: 'Configuraciones globales' })
  @ApiOkResponse({ description: 'Configuraciones globales activas' })
  async globalSettings() {
    const data = await this.crudService.getSettings();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/settings/company */
  @Get('settings/company')
  @ApiOperation({ summary: 'Configuraciones de la empresa' })
  @ApiOkResponse({ description: 'Configuraciones de empresa' })
  async companySettings(@CurrentUser() user: User) {
    const companyId = (user as any).companyId;
    const data = await this.crudService.getCompanySettings(companyId);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/settings/report-header */
  @Get('settings/report-header')
  @ApiOperation({ summary: 'Encabezado de reportes de la empresa (legacy)' })
  @ApiOkResponse({ description: 'Encabezado de reportes (legacy)' })
  async reportHeader(@CurrentUser() user: User) {
    const companyId = (user as any).companyId;
    const data = await this.crudService.getReportHeader(companyId);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/settings/reports */
  @Get('settings/reports')
  @ApiOperation({ summary: 'Encabezado de reportes de la empresa' })
  @ApiOkResponse({ description: 'Encabezado de reportes' })
  async reportsHeader(@CurrentUser() user: User) {
    const companyId = (user as any).companyId;
    const data = await this.crudService.getReportHeader(companyId);
    return { dataRecords: { data } };
  }

  /** PUT /api/v1/settings/report-header */
  @Put('settings/report-header')
  @ApiOperation({ summary: 'Guardar encabezado de reportes (legacy)' })
  @ApiBody({ type: Object })
  @ApiOkResponse({ description: 'Encabezado actualizado (legacy)' })
  async updateReportHeader(
    @Body() body: Partial<ReportHeader>,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.crudService.upsertReportHeader(companyId, body);
    return { dataRecords: { data } };
  }

  /** PUT /api/v1/settings/reports/:id */
  @Put('settings/reports/:id')
  @ApiOperation({ summary: 'Guardar encabezado de reportes' })
  @ApiParam({ name: 'id', type: Number })
  @ApiBody({ type: Object })
  @ApiOkResponse({ description: 'Encabezado actualizado' })
  async updateReportsHeader(
    @Body() body: Partial<ReportHeader>,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.crudService.upsertReportHeader(companyId, body);
    return { dataRecords: { data } };
  }

  /**
   * GET /api/v1/crud
   */
  @Get('crud')
  @ApiOperation({ summary: 'CRUD genérico - listar registros' })
  @ApiQuery({ name: 'tbPrefix', required: true, type: String })
  @ApiQuery({ name: 'limit', required: false, type: Number })
  @ApiQuery({ name: 'page', required: false, type: Number })
  @ApiQuery({ name: 'query', required: false, type: String })
  @ApiOkResponse({ description: 'Listado paginado de registros dinámicos' })
  async crudIndex(
    @Query() query: Record<string, any>,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    return this.crudService.crudRead(query, null, companyId);
  }

  /**
   * POST /api/v1/crud
   */
  @Post('crud')
  @ApiOperation({ summary: 'CRUD genérico - crear registro(s)' })
  @ApiBody({ type: Object })
  @ApiOkResponse({ description: 'Registro(s) creado(s)' })
  async crudStore(
    @Body() body: Record<string, any>,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.crudService.crudCreate(body, companyId);
    return { message: 'Registro creado correctamente.', dataRecords: { data } };
  }

  /**
   * GET /api/v1/crud/:id
   */
  @Get('crud/:id')
  @ApiOperation({ summary: 'CRUD genérico - detalle por id' })
  @ApiParam({ name: 'id', type: Number })
  @ApiQuery({ name: 'tbPrefix', required: true, type: String })
  @ApiOkResponse({ description: 'Detalle de registro dinámico' })
  async crudShow(
    @Param('id', ParseIntPipe) id: number,
    @Query() query: Record<string, any>,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    return this.crudService.crudRead(query, id, companyId);
  }

  /**
   * PUT /api/v1/crud/:id
   */
  @Put('crud/:id')
  @ApiOperation({ summary: 'CRUD genérico - actualizar registro(s)' })
  @ApiParam({ name: 'id', type: Number })
  @ApiBody({ type: Object })
  @ApiOkResponse({ description: 'Registro(s) actualizado(s)' })
  async crudUpdate(
    @Param('id', ParseIntPipe) id: number,
    @Body() body: Record<string, any>,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    await this.crudService.crudUpdate(id, body, companyId);
    return { message: 'Registro actualizado correctamente.' };
  }

  /**
   * DELETE /api/v1/crud/:id
   */
  @Delete('crud/:id')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'CRUD genérico - eliminar registro' })
  @ApiParam({ name: 'id', type: Number })
  @ApiQuery({ name: 'tbPrefix', required: true, type: String })
  @ApiOkResponse({ description: 'Registro eliminado' })
  async crudDestroy(
    @Param('id', ParseIntPipe) id: number,
    @Query() query: Record<string, any>,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.crudService.crudDelete(id, query, companyId);
    return { dataRecords: { data } };
  }
}
