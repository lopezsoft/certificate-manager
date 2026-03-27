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
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { User } from '@database/entities/user.entity';
import { TokensService } from './tokens.service';
import { CreateTokenDto } from './dto/create-token.dto';

@ApiTags('Tokens')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('tokens')
export class TokensController {
  constructor(private readonly tokensService: TokensService) { }

  /** GET /api/v1/tokens */
  @Get()
  @ApiOperation({ summary: 'Listar tokens del usuario' })
  async index(@CurrentUser() user: User) {
    const data = await this.tokensService.listForUser(user.id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/tokens */
  @Post()
  @ApiOperation({ summary: 'Crear personal access token' })
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
  async show(@Param('id', ParseIntPipe) id: number, @CurrentUser() user: User) {
    const data = await this.tokensService.findOneForUser(id, user.id);
    return { dataRecords: { data } };
  }

  /** POST /api/v1/tokens/:id/renew */
  @Post(':id/renew')
  @ApiOperation({ summary: 'Renovar token por ID' })
  async renew(@Param('id', ParseIntPipe) id: number, @CurrentUser() user: User) {
    const data = await this.tokensService.renew(id, user.id);
    return { message: 'Token renovado exitosamente.', dataRecords: { data } };
  }

  /** DELETE /api/v1/tokens */
  @Delete()
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Revocar todos los tokens del usuario' })
  async revokeAll(@CurrentUser() user: User) {
    await this.tokensService.revokeAll(user.id);
    return { message: 'Todos los tokens han sido revocados.' };
  }

  /** POST /api/v1/tokens/revoke-all */
  @Post('revoke-all')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Revocar todos los tokens del usuario (compat Laravel)' })
  async revokeAllPost(@CurrentUser() user: User) {
    await this.tokensService.revokeAll(user.id);
    return { message: 'Todos los tokens han sido revocados.' };
  }
}
