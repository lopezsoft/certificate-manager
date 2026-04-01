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
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiBody,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTooManyRequestsResponse,
  ApiTags,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { User } from '@database/entities/user.entity';
import { TokensService } from './tokens.service';
import { CreateTokenDto } from './dto/create-token.dto';
import { EndpointRateLimit } from '@common/decorators/rate-limit.decorator';
import { EndpointRateLimitGuard } from '@common/guards/endpoint-rate-limit.guard';

@ApiTags('Tokens')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('tokens')
export class TokensController {
  constructor(private readonly tokensService: TokensService) { }

  /** GET /api/v1/tokens */
  @Get()
  @ApiOperation({ summary: 'Listar tokens del usuario' })
  @ApiOkResponse({ description: 'Listado de tokens activos del usuario' })
  async index(@CurrentUser() user: User) {
    const data = await this.tokensService.listForUser(user.id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/tokens */
  @Post()
  @UseGuards(JwtAuthGuard, EndpointRateLimitGuard)
  @EndpointRateLimit({ max: 10, windowMs: 60_000 })
  @ApiOperation({ summary: 'Crear personal access token' })
  @ApiBody({ type: CreateTokenDto })
  @ApiCreatedResponse({ description: 'Token creado' })
  @ApiTooManyRequestsResponse({ description: 'Demasiadas solicitudes' })
  async store(@Body() dto: CreateTokenDto, @CurrentUser() user: User) {
    const companyId = (user as any).companyId;
    const result = await this.tokensService.create(user.id, dto, companyId);
    return {
      message:
        'Token creado exitosamente. Guárdalo en un lugar seguro, no se mostrará de nuevo.',
      dataRecords: { data: result },
    };
  }

  /** DELETE /api/v1/tokens/:id */
  @Delete(':id')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Revocar token por ID' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Token revocado' })
  async revoke(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: User,
  ) {
    await this.tokensService.revoke(id, user.id);
    return { message: 'Token revocado exitosamente.' };
  }

  /** GET /api/v1/tokens/:id */
  @Get(':id')
  @ApiOperation({ summary: 'Obtener token por ID' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Detalle del token' })
  async show(@Param('id', ParseIntPipe) id: number, @CurrentUser() user: User) {
    const data = await this.tokensService.findOneForUser(id, user.id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/tokens/:id/renew */
  @Post(':id/renew')
  @ApiOperation({ summary: 'Renovar token por ID' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Token renovado' })
  async renew(@Param('id', ParseIntPipe) id: number, @CurrentUser() user: User) {
    const data = await this.tokensService.renew(id, user.id);
    return { message: 'Token renovado exitosamente.', dataRecords: { data } };
  }

  /** DELETE /api/v1/tokens */
  @Delete()
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Revocar todos los tokens del usuario' })
  @ApiOkResponse({ description: 'Todos los tokens revocados' })
  async revokeAll(@CurrentUser() user: User) {
    await this.tokensService.revokeAll(user.id);
    return { message: 'Todos los tokens han sido revocados.' };
  }

  /** POST /api/v1/tokens/revoke-all */
  @Post('revoke-all')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Revocar todos los tokens del usuario (compat Laravel)' })
  @ApiOkResponse({ description: 'Todos los tokens revocados' })
  async revokeAllPost(@CurrentUser() user: User) {
    await this.tokensService.revokeAll(user.id);
    return { message: 'Todos los tokens han sido revocados.' };
  }
}
