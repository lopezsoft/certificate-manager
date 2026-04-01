import { createParamDecorator, ExecutionContext } from '@nestjs/common';
import { FastifyRequest } from 'fastify';

/**
 * Extrae la empresa activa del request (inyectada por CompanyGuard o JwtStrategy).
 * Uso: @CurrentCompany() company: Company
 */
export const CurrentCompany = createParamDecorator(
  (_data: unknown, ctx: ExecutionContext) => {
    const request = ctx
      .switchToHttp()
      .getRequest<FastifyRequest & { company: any }>();
    return request.company;
  },
);
