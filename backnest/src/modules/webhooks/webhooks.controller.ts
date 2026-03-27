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
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
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
  async availableEvents() {
    const data = this.webhooksService.getAvailableEvents();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/webhooks */
  @Get()
  @ApiOperation({ summary: 'Listar webhook endpoints de la empresa' })
  async index(@CurrentUser() user: User) {
    const companyId = (user as any).companyId;
    const data = await this.webhooksService.findAll(companyId);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/webhooks/:id */
  @Get(':id')
  @ApiOperation({ summary: 'Obtener endpoint por ID' })
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
  async deliveries(@Param('id', ParseIntPipe) id: number) {
    const data = await this.webhooksService.getDeliveries(id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/webhooks/:id/rotate-secret */
  @Post(':id/rotate-secret')
  @ApiOperation({ summary: 'Rotar secreto de firma del webhook endpoint' })
  async rotateSecret(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    const companyId = (user as any).companyId;
    const data = await this.webhooksService.rotateSecret(id, companyId);
    return { dataRecords: { data } };
  }
}
