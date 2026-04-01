import { SetMetadata } from '@nestjs/common';

export const ENDPOINT_RATE_LIMIT_KEY = 'endpoint-rate-limit';

export interface EndpointRateLimitOptions {
  max: number;
  windowMs: number;
}

export const EndpointRateLimit = (options: EndpointRateLimitOptions) =>
  SetMetadata(ENDPOINT_RATE_LIMIT_KEY, options);
