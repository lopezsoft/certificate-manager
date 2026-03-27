import {
  Controller,
  Get,
  HttpCode,
  HttpStatus,
  Param,
  Post,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { User } from '@database/entities/user.entity';
import { NotificationsService } from './notifications.service';

@ApiTags('Notifications')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller()
export class NotificationsController {
  constructor(private readonly notificationsService: NotificationsService) { }

  /** GET /api/v1/notifications */
  @Get('notifications')
  @ApiOperation({ summary: 'Listar notificaciones del usuario' })
  async index(@CurrentUser() user: User) {
    const data = await this.notificationsService.getForUser(user.id);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/notifications/unread */
  @Get('notifications/unread')
  @ApiOperation({ summary: 'Notificaciones no leídas' })
  async unread(@CurrentUser() user: User) {
    const data = await this.notificationsService.getUnreadForUser(user.id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/notifications/:id/read */
  @Post('notifications/:id/read')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Marcar notificación como leída' })
  async markRead(@Param('id') id: string, @CurrentUser() user: User) {
    const data = await this.notificationsService.markAsRead(id, user.id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/notifications/read-all */
  @Post('notifications/read-all')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Marcar todas las notificaciones como leídas' })
  async markAllRead(@CurrentUser() user: User) {
    await this.notificationsService.markAllAsRead(user.id);
    return { message: 'Todas las notificaciones marcadas como leídas.' };
  }

  /** GET /api/v1/certificates/expiring */
  @Get('certificates/expiring')
  @ApiOperation({ summary: 'Listar certificados próximos a vencer' })
  async expiring() {
    const data = await this.notificationsService.getExpiringCertificates(30);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/admin/certificates/notify-now */
  @Post('admin/certificates/notify-now')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Disparar notificaciones de vencimiento manualmente' })
  async notifyNow() {
    const data = await this.notificationsService.triggerExpiringNotificationsNow();
    return { dataRecords: { data } };
  }
}
