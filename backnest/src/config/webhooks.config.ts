import { registerAs } from '@nestjs/config';

export default registerAs('webhooks', () => ({
  queue: process.env.WEBHOOK_QUEUE ?? 'webhooks',
  timeout: parseInt(process.env.WEBHOOK_TIMEOUT ?? '10', 10),
  maxFailures: parseInt(process.env.WEBHOOK_MAX_FAILURES ?? '10', 10),
  maxEndpointsPerCompany: parseInt(process.env.WEBHOOK_MAX_ENDPOINTS_PER_COMPANY ?? '5', 10),
  deliveryLogRetentionDays: parseInt(process.env.WEBHOOK_DELIVERY_LOG_RETENTION_DAYS ?? '30', 10),
}));
