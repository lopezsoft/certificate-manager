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
  ApiBody,
  ApiBearerAuth,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiQuery,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { PaginationQueryDto } from '@common/dto/pagination-query.dto';
import { User } from '@database/entities/user.entity';
import { CompaniesService } from '@modules/companies/companies.service';
import { CreateCompanyDto } from '@modules/companies/dto/create-company.dto';
import { UpdateCompanyDto } from '@modules/companies/dto/update-company.dto';

@ApiTags('Companies')
@ApiBearerAuth()
@ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
@UseGuards(JwtAuthGuard)
@Controller()
export class CompaniesController {
  constructor(private readonly companiesService: CompaniesService) { }

  /** GET /api/v1/companies */
  @Get('companies')
  @ApiOperation({ summary: 'Listar empresas (paginado)' })
  @ApiQuery({ name: 'page', required: false, type: Number })
  @ApiQuery({ name: 'limit', required: false, type: Number })
  @ApiQuery({ name: 'query', required: false, type: String })
  @ApiOkResponse({ description: 'Listado paginado de empresas' })
  async index(@Query() query: PaginationQueryDto) {
    return this.companiesService.findAll(query);
  }

  /** GET /api/v1/companies/:id */
  @Get('companies/:id')
  @ApiOperation({ summary: 'Obtener empresa por ID' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Detalle de empresa' })
  async show(@Param('id', ParseIntPipe) id: number) {
    const data = await this.companiesService.findOne(id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/companies */
  @Post('companies')
  @ApiOperation({ summary: 'Crear empresa' })
  @ApiBody({ type: CreateCompanyDto })
  @ApiCreatedResponse({ description: 'Empresa creada' })
  async store(@Body() dto: CreateCompanyDto) {
    const data = await this.companiesService.create(dto);
    return { message: 'Recurso creado exitosamente.', dataRecords: { data } };
  }

  /** PUT /api/v1/companies/:id */
  @Put('companies/:id')
  @ApiOperation({ summary: 'Actualizar empresa' })
  @ApiParam({ name: 'id', type: Number })
  @ApiBody({ type: UpdateCompanyDto })
  @ApiOkResponse({ description: 'Empresa actualizada' })
  async update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateCompanyDto,
  ) {
    const data = await this.companiesService.update(id, dto);
    return { dataRecords: { data } };
  }

  /** DELETE /api/v1/companies/:id */
  @Delete('companies/:id')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Eliminar empresa' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Empresa eliminada' })
  async destroy(@Param('id', ParseIntPipe) id: number) {
    await this.companiesService.remove(id);
    return { message: 'Recurso eliminado exitosamente.' };
  }

  /** GET /api/v1/company */
  @Get('company')
  @ApiOperation({ summary: 'Empresa asociada al usuario autenticado' })
  @ApiOkResponse({ description: 'Empresa del usuario autenticado' })
  async currentCompany(@CurrentUser() user: User) {
    const companyId = user.companyId;
    const data = await this.companiesService.findOne(companyId);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/company/settings */
  @Get('company/settings')
  @ApiOperation({ summary: 'Configuraciones de la empresa autenticada' })
  @ApiOkResponse({ description: 'Configuraciones de la empresa' })
  async companySettings(@CurrentUser() user: User) {
    const companyId = user.companyId;
    const data = await this.companiesService.getCompanySettings(companyId);
    return { dataRecords: { data } };
  }

  /** PUT /api/v1/company/settings */
  @Put('company/settings')
  @ApiOperation({ summary: 'Actualizar configuraciones de empresa' })
  @ApiBody({
    schema: {
      type: 'object',
      properties: {
        settings: {
          type: 'array',
          items: {
            type: 'object',
            properties: {
              general_setting_id: { type: 'number' },
              value: { type: 'string' },
              is_active: { type: 'boolean' },
            },
          },
        },
      },
    },
  })
  @ApiOkResponse({ description: 'Configuraciones actualizadas' })
  async updateCompanySettings(
    @Body()
    payload: {
      settings?: Array<{
        general_setting_id: number;
        value?: string;
        is_active?: boolean;
      }>;
    },
    @CurrentUser() user: User,
  ) {
    const companyId = user.companyId;
    const data = await this.companiesService.updateCompanySettings(companyId, payload);
    return { dataRecords: { data } };
  }
}
