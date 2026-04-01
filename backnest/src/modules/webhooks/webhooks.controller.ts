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
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiBody,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTags,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { User } from '@database/entities/user.entity';
import { WebhooksService } from './webhooks.service';
import { CreateWebhookEndpointDto } from './dto/create-webhook-endpoint.dto';
import { UpdateWebhookEndpointDto } from './dto/update-webhook-endpoint.dto';

@ApiTags('Webhooks')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('webhooks')
export class WebhooksController {
  constructor(private readonly webhooksService: WebhooksService) { }

  /** GET /api/v1/webhooks/events */
  @Get('events')
  @ApiOperation({ summary: 'Eventos disponibles para suscribir webhooks' })
  @ApiOkResponse({ description: 'Listado de eventos disponibles' })
  async availableEvents() {
    const data = this.webhooksService.getAvailableEvents();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/webhooks */
  @Get()
  @ApiOperation({ summary: 'Listar webhook endpoints de la empresa' })
  @ApiOkResponse({ description: 'Listado de webhooks de la empresa' })
  async index(@CurrentUser() user: User) {
    const companyId = (user as any).companyId;
    const data = await this.webhooksService.findAll(companyId);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/webhooks/:id */
  @Get(':id')
  @ApiOperation({ summary: 'Obtener endpoint por ID' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Detalle de endpoint webhook' })
  async show(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.webhooksService.findOne(id, companyId);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/webhooks */
  @Post()
  @ApiOperation({ summary: 'Crear webhook endpoint' })
  @ApiBody({ type: CreateWebhookEndpointDto })
  @ApiCreatedResponse({ description: 'Webhook endpoint creado' })
  async store(
    @Body() dto: CreateWebhookEndpointDto,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.webhooksService.create(companyId, dto);
    return { message: 'Recurso creado exitosamente.', dataRecords: { data } };
  }

  /** PUT /api/v1/webhooks/:id */
  @Put(':id')
  @ApiOperation({ summary: 'Actualizar webhook endpoint' })
  @ApiParam({ name: 'id', type: Number })
  @ApiBody({ type: UpdateWebhookEndpointDto })
  @ApiOkResponse({ description: 'Webhook endpoint actualizado' })
  async update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateWebhookEndpointDto,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.webhooksService.update(id, companyId, dto);
    return { dataRecords: { data } };
  }

  /** DELETE /api/v1/webhooks/:id */
  @Delete(':id')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Eliminar webhook endpoint (soft delete)' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Webhook endpoint eliminado' })
  async destroy(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    await this.webhooksService.remove(id, companyId);
    return { message: 'Recurso eliminado exitosamente.' };
  }

  /** GET /api/v1/webhooks/:id/deliveries */
  @Get(':id/deliveries')
  @ApiOperation({ summary: 'Historial de entregas de un endpoint' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Historial de entregas del endpoint' })
  async deliveries(@Param('id', ParseIntPipe) id: number) {
    const data = await this.webhooksService.getDeliveries(id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/webhooks/:id/rotate-secret */
  @Post(':id/rotate-secret')
  @ApiOperation({ summary: 'Rotar secreto de firma del webhook endpoint' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Secreto rotado exitosamente' })
  async rotateSecret(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.webhooksService.rotateSecret(id, companyId);
    return { dataRecords: { data } };
  }
}
