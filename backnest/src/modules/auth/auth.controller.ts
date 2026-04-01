import {
  Body,
  Controller,
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
  ApiBody,
  ApiBearerAuth,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiProperty,
  ApiTooManyRequestsResponse,
  ApiTags,
} from '@nestjs/swagger';
import { AuthService } from './auth.service';
import { LoginDto } from './dto/login.dto';
import { RegisterDto } from './dto/register.dto';
import { ForgotPasswordDto } from './dto/forgot-password.dto';
import { ResetPasswordDto } from './dto/reset-password.dto';
import { JwtAuthGuard } from './guards/jwt-auth.guard';
import { CurrentUser } from '@common/decorators/current-user.decorator';
import { User } from '@database/entities/user.entity';
import { IsEmail, IsNotEmpty } from 'class-validator';
import { EndpointRateLimit } from '@common/decorators/rate-limit.decorator';
import { EndpointRateLimitGuard } from '@common/guards/endpoint-rate-limit.guard';

class EmailVerificationNotificationDto {
  @ApiProperty({ example: 'user@example.com' })
  @IsEmail()
  @IsNotEmpty()
  email: string;
}

@ApiTags('Auth')
@Controller()
export class AuthController {
  constructor(private readonly authService: AuthService) { }

  /**
   * GET /api/v1/auth/types
   * Tipos de usuario disponibles
   */
  @Get('auth/types')
  @ApiOperation({ summary: 'Obtener tipos de usuario' })
  @ApiOkResponse({ description: 'Listado de tipos de usuario' })
  async types() {
    const data = await this.authService.getUserTypes();
    return { dataRecords: { data } };
  }

  /**
   * POST /api/v1/auth/login
   */
  @Post('auth/login')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Iniciar sesión' })
  @ApiBody({ type: LoginDto })
  @ApiOkResponse({ description: 'Login exitoso con token' })
  async login(@Body() dto: LoginDto) {
    const result = await this.authService.login(dto);
    return { dataRecords: result };
  }

  /**
   * POST /api/v1/auth/register
   */
  @Post('register')
  @ApiOperation({ summary: 'Registrar nuevo usuario' })
  @ApiBody({ type: RegisterDto })
  @ApiCreatedResponse({ description: 'Usuario registrado exitosamente' })
  async register(@Body() dto: RegisterDto) {
    const result = await this.authService.register(dto);
    return { message: result.message };
  }

  /**
   * POST /api/v1/auth/forgot-password
   */
  @Post('forgot-password')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Solicitar recuperación de contraseña' })
  @ApiBody({ type: ForgotPasswordDto })
  @ApiOkResponse({ description: 'Correo de recuperación procesado' })
  async forgotPassword(@Body() dto: ForgotPasswordDto) {
    const result = await this.authService.forgotPassword(dto);
    return { message: result.message };
  }

  /**
   * POST /api/v1/auth/reset-password
   */
  @Post('reset-password')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Restablecer contraseña' })
  @ApiBody({ type: ResetPasswordDto })
  @ApiOkResponse({ description: 'Contraseña restablecida' })
  async resetPassword(@Body() dto: ResetPasswordDto) {
    const result = await this.authService.resetPassword(dto);
    return { message: result.message };
  }

  /**
   * GET /api/v1/auth/user
   * Perfil del usuario autenticado
   */
  @Get('auth/user')
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Perfil del usuario autenticado' })
  @ApiOkResponse({ description: 'Perfil autenticado' })
  async authUser(@CurrentUser() user: User) {
    const data = await this.authService.getProfile(user.id);
    return { dataRecords: { data } };
  }

  /**
   * GET /api/v1/profile
   * Alias Laravel del perfil autenticado
   */
  @Get('profile')
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Perfil del usuario autenticado (alias profile)' })
  @ApiOkResponse({ description: 'Perfil autenticado' })
  async profile(@CurrentUser() user: User) {
    const data = await this.authService.getProfile(user.id);
    return { dataRecords: { data } };
  }

  /**
   * POST /api/v1/auth/logout
   * Con JWT stateless simplemente se indica al cliente que descarte el token.
   */
  @Get('auth/logout')
  @HttpCode(HttpStatus.OK)
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Cerrar sesión (GET Laravel)' })
  @ApiOkResponse({ description: 'Sesión cerrada' })
  async logoutGet() {
    return { message: 'Sesión cerrada exitosamente.' };
  }

  @Post('auth/logout')
  @HttpCode(HttpStatus.OK)
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Cerrar sesión (POST compat adicional)' })
  @ApiOkResponse({ description: 'Sesión cerrada' })
  async logoutPost() {
    return { message: 'Sesión cerrada exitosamente.' };
  }

  @Get('verify-email/:id/:hash')
  @UseGuards(EndpointRateLimitGuard)
  @EndpointRateLimit({ max: 6, windowMs: 60_000 })
  @ApiOperation({ summary: 'Verificar email del usuario' })
  @ApiParam({ name: 'id', type: Number })
  @ApiParam({ name: 'hash', type: String })
  @ApiOkResponse({ description: 'Email verificado' })
  @ApiTooManyRequestsResponse({ description: 'Demasiadas solicitudes' })
  async verifyEmail(@Param('id', ParseIntPipe) id: number) {
    return this.authService.verifyEmail(id);
  }

  @Post('email/verification-notification')
  @ApiOperation({ summary: 'Reenviar notificación de verificación de email' })
  @ApiBody({ type: EmailVerificationNotificationDto })
  @ApiOkResponse({ description: 'Notificación de verificación enviada' })
  async resendVerification(@Body() dto: EmailVerificationNotificationDto) {
    return this.authService.sendEmailVerificationNotificationByEmail(dto.email);
  }

  @Get('profile/types')
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Obtener tipos de usuario para perfil' })
  @ApiOkResponse({ description: 'Tipos de usuario para perfil' })
  async profileTypes() {
    const data = await this.authService.getUserTypes();
    return { dataRecords: { data } };
  }

  @Put('profile/:id')
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Actualizar perfil del usuario autenticado' })
  @ApiParam({ name: 'id', type: Number })
  @ApiOkResponse({ description: 'Perfil actualizado' })
  async updateProfile(
    @Param('id', ParseIntPipe) id: number,
    @Body()
    body: {
      first_name?: string;
      last_name?: string;
      email?: string;
      password?: string;
    },
    @CurrentUser() user: User,
  ) {
    const data = await this.authService.updateProfile(id, body, user.id);
    return { dataRecords: { data } };
  }
}
