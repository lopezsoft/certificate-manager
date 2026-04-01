import {
  CanActivate,
  ExecutionContext,
  HttpException,
  HttpStatus,
  Injectable,
} from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { FastifyRequest, FastifyReply } from 'fastify';
import {
  ENDPOINT_RATE_LIMIT_KEY,
  EndpointRateLimitOptions,
} from '@common/decorators/rate-limit.decorator';

interface Bucket {
  count: number;
  resetAt: number;
}

@Injectable()
export class EndpointRateLimitGuard implements CanActivate {
  private readonly buckets = new Map<string, Bucket>();

  constructor(private readonly reflector: Reflector) { }

  canActivate(context: ExecutionContext): boolean {
    const options = this.reflector.getAllAndOverride<EndpointRateLimitOptions>(
      ENDPOINT_RATE_LIMIT_KEY,
      [context.getHandler(), context.getClass()],
    );

    if (!options) {
      return true;
    }

    const http = context.switchToHttp();
    const request = http.getRequest<FastifyRequest>();
    const reply = http.getResponse<FastifyReply>();

    const routePath = `${request.method}:${request.routerPath ?? request.url.split('?')[0]}`;
    const identifier = this.getIdentifier(request);
    const key = `${routePath}:${identifier}`;

    const now = Date.now();
    const existing = this.buckets.get(key);

    if (!existing || now >= existing.resetAt) {
      this.buckets.set(key, {
        count: 1,
        resetAt: now + options.windowMs,
      });
      reply.header('X-RateLimit-Limit', String(options.max));
      reply.header('X-RateLimit-Remaining', String(options.max - 1));
      return true;
    }

    if (existing.count >= options.max) {
      const retryAfter = Math.ceil((existing.resetAt - now) / 1000);
      reply.header('Retry-After', String(retryAfter));
      throw new HttpException('Too Many Requests.', HttpStatus.TOO_MANY_REQUESTS);
    }

    existing.count += 1;
    this.buckets.set(key, existing);
    reply.header('X-RateLimit-Limit', String(options.max));
    reply.header('X-RateLimit-Remaining', String(options.max - existing.count));
    return true;
  }

  private getIdentifier(request: FastifyRequest): string {
    const forwarded = request.headers['x-forwarded-for'];
    if (typeof forwarded === 'string' && forwarded.length > 0) {
      return forwarded.split(',')[0].trim();
    }

    if (Array.isArray(forwarded) && forwarded.length > 0) {
      return forwarded[0];
    }

    return request.ip;
  }
}
