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
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBadRequestResponse,
  ApiBearerAuth,
  ApiBody,
  ApiCreatedResponse,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiProperty,
  ApiTooManyRequestsResponse,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import { FastifyRequest } from 'fastify';
import { AuthService } from '@modules/auth/auth.service';
import { LoginDto } from '@modules/auth/dto/login.dto';
import { RegisterDto } from '@modules/auth/dto/register.dto';
import { ForgotPasswordDto } from '@modules/auth/dto/forgot-password.dto';
import { ResetPasswordDto } from '@modules/auth/dto/reset-password.dto';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
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
   * Autentica al usuario y retorna token OAuth Bearer (compatible Laravel Passport).
   */
  @Post('auth/login')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Iniciar sesión', description: 'Autentica al usuario y retorna un token OAuth Bearer compatible con Laravel Passport.' })
  @ApiBody({ type: LoginDto })
  @ApiOkResponse({ description: 'Login exitoso con token Bearer' })
  @ApiBadRequestResponse({ description: 'Datos de entrada inválidos' })
  @ApiUnauthorizedResponse({ description: 'Credenciales incorrectas' })
  async login(
    @Body() dto: LoginDto,
    @Req() request: FastifyRequest,
  ) {
    const clientIp = request.ip;
    const result = await this.authService.login(dto, clientIp);
    return result;
  }

  /**
   * POST /api/v1/auth/register
   */
  @Post('register')
  @ApiOperation({ summary: 'Registrar nuevo usuario', description: 'Crea un nuevo usuario en el sistema. Requiere verificación de email posterior.' })
  @ApiBody({ type: RegisterDto })
  @ApiCreatedResponse({ description: 'Usuario registrado exitosamente' })
  @ApiBadRequestResponse({ description: 'Datos de entrada inválidos o email ya registrado' })
  async register(@Body() dto: RegisterDto) {
    const result = await this.authService.register(dto);
    return { message: result.message };
  }

  /**
   * POST /api/v1/auth/forgot-password
   */
  @Post('forgot-password')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Solicitar recuperación de contraseña', description: 'Envía un correo con el enlace de restablecimiento de contraseña al email indicado.' })
  @ApiBody({ type: ForgotPasswordDto })
  @ApiOkResponse({ description: 'Correo de recuperación procesado' })
  @ApiBadRequestResponse({ description: 'Email inválido o no registrado' })
  async forgotPassword(@Body() dto: ForgotPasswordDto) {
    const result = await this.authService.forgotPassword(dto);
    return { message: result.message };
  }

  /**
   * POST /api/v1/auth/reset-password
   */
  @Post('reset-password')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Restablecer contraseña', description: 'Restablece la contraseña del usuario utilizando el token enviado por correo.' })
  @ApiBody({ type: ResetPasswordDto })
  @ApiOkResponse({ description: 'Contraseña restablecida' })
  @ApiBadRequestResponse({ description: 'Token inválido, expirado o datos incorrectos' })
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
  @ApiOperation({ summary: 'Perfil del usuario autenticado', description: 'Retorna los datos completos del usuario autenticado junto con su empresa y permisos.' })
  @ApiOkResponse({ description: 'Perfil autenticado' })
  @ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
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
  @ApiOperation({ summary: 'Perfil del usuario autenticado (alias profile)', description: 'Alias compatible con Laravel para obtener el perfil del usuario autenticado.' })
  @ApiOkResponse({ description: 'Perfil autenticado' })
  @ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
  async profile(@CurrentUser() user: User) {
    const data = await this.authService.getProfile(user.id);
    return { dataRecords: { data } };
  }

  /**
   * GET /api/v1/auth/logout
   * Revoca el token Bearer activo (compatible Laravel Passport).
   */
  @Get('auth/logout')
  @HttpCode(HttpStatus.OK)
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Cerrar sesión (GET)', description: 'Revoca el token Bearer activo. Compatible con Laravel Passport.' })
  @ApiOkResponse({ description: 'Sesión cerrada, token revocado' })
  @ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
  async logoutGet(
    @CurrentUser() user: User,
    @Req() request: FastifyRequest,
  ) {
    const rawToken = this.authService.extractTokenFromHeader(
      request.headers.authorization,
    );
    return this.authService.logout(rawToken, user, request.ip);
  }

  @Post('auth/logout')
  @HttpCode(HttpStatus.OK)
  @UseGuards(JwtAuthGuard)
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Cerrar sesión (POST)', description: 'Revoca el token Bearer activo. Endpoint POST adicional de compatibilidad.' })
  @ApiOkResponse({ description: 'Sesión cerrada, token revocado' })
  @ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
  async logoutPost(
    @CurrentUser() user: User,
    @Req() request: FastifyRequest,
  ) {
    const rawToken = this.authService.extractTokenFromHeader(
      request.headers.authorization,
    );
    return this.authService.logout(rawToken, user, request.ip);
  }

  @Get('verify-email/:id/:hash')
  @UseGuards(EndpointRateLimitGuard)
  @EndpointRateLimit({ max: 6, windowMs: 60_000 })
  @ApiOperation({ summary: 'Verificar email del usuario', description: 'Verifica la dirección de correo del usuario a partir del enlace enviado por email.' })
  @ApiParam({ name: 'id', type: Number, description: 'ID del usuario' })
  @ApiParam({ name: 'hash', type: String, description: 'Hash de verificación' })
  @ApiOkResponse({ description: 'Email verificado exitosamente' })
  @ApiNotFoundResponse({ description: 'Usuario no encontrado' })
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
  @ApiOperation({ summary: 'Actualizar perfil del usuario autenticado', description: 'Permite actualizar nombre, apellido, email y contraseña del perfil propio.' })
  @ApiParam({ name: 'id', type: Number, description: 'ID del usuario a actualizar' })
  @ApiOkResponse({ description: 'Perfil actualizado' })
  @ApiBadRequestResponse({ description: 'Datos inválidos' })
  @ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
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
