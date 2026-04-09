import { Injectable, UnauthorizedException } from '@nestjs/common';
import { PassportStrategy } from '@nestjs/passport';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, MoreThan } from 'typeorm';
import { Strategy } from 'passport-custom';
import { createHash } from 'crypto';
import { FastifyRequest } from 'fastify';
import { User } from '@database/entities/user.entity';
import { OAuthAccessToken } from '@database/entities/oauth-access-token.entity';

/**
 * Estrategia de autenticación compatible con Laravel Passport.
 *
 * Extrae el token Bearer del header Authorization, calcula su SHA-256
 * y lo busca en la tabla `oauth_access_tokens`. Si es válido y no está
 * revocado ni expirado, carga el usuario con su relación `userType`.
 *
 * Se registra con el nombre 'jwt' para mantener compatibilidad con
 * el guard `JwtAuthGuard` ya utilizado en todos los controllers.
 */
@Injectable()
export class BearerTokenStrategy extends PassportStrategy(Strategy, 'jwt') {
  constructor(
    @InjectRepository(OAuthAccessToken)
    private readonly tokenRepo: Repository<OAuthAccessToken>,
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
  ) {
    super();
  }

  /**
   * Valida el token Bearer del request.
   * @param request FastifyRequest con el header Authorization
   * @returns User autenticado
   */
  async validate(request: FastifyRequest): Promise<User> {
    const authHeader = request.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      throw new UnauthorizedException('Token de acceso no proporcionado.');
    }

    const rawToken = authHeader.substring(7); // quitar "Bearer "

    if (!rawToken || rawToken.trim() === '' || rawToken === '*') {
      throw new UnauthorizedException('Token de acceso inválido.');
    }

    // Buscar el token en la tabla oauth_access_tokens por su ID
    // Laravel Passport almacena el JWT cuyo JTI es el ID del token
    // En nuestra implementación, usamos el token como ID directamente
    const tokenRecord = await this.tokenRepo.findOne({
      where: {
        id: rawToken,
        revoked: false,
      },
    });

    if (!tokenRecord) {
      throw new UnauthorizedException('Sesión inválida o token revocado.');
    }

    // Verificar expiración
    if (tokenRecord.expiresAt && new Date() > tokenRecord.expiresAt) {
      throw new UnauthorizedException('Token expirado. Por favor inicie sesión nuevamente.');
    }

    // Cargar usuario con relaciones
    const user = await this.userRepo.findOne({
      where: { id: tokenRecord.userId, active: 1 },
      relations: ['userType'],
    });

    if (!user) {
      throw new UnauthorizedException('Sesión inválida o usuario inactivo.');
    }

    return user;
  }
}

