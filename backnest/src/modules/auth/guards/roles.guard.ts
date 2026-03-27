import {
  CanActivate,
  ExecutionContext,
  ForbiddenException,
  Injectable,
} from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { FastifyRequest } from 'fastify';
import { User } from '@database/entities/user.entity';

export const ROLES_KEY = 'roles';

/**
 * Guard que valida roles de usuario.
 * Uso: @SetMetadata(ROLES_KEY, ['admin', 'manager']) + @UseGuards(JwtAuthGuard, RolesGuard)
 */
@Injectable()
export class RolesGuard implements CanActivate {
  constructor(private readonly reflector: Reflector) { }

  canActivate(context: ExecutionContext): boolean {
    const requiredRoles = this.reflector.getAllAndOverride<string[]>(ROLES_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);

    if (!requiredRoles || requiredRoles.length === 0) {
      return true;
    }

    const request = context
      .switchToHttp()
      .getRequest<FastifyRequest & { user: User }>();
    const user = request.user;

    if (!user || !user.userType) {
      throw new ForbiddenException('Acceso denegado.');
    }

    const hasRole = requiredRoles.includes(user.userType.type);
    if (!hasRole) {
      throw new ForbiddenException('No tienes permisos para esta acción.');
    }

    return true;
  }
}
