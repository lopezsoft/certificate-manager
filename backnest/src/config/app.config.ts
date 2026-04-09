import { registerAs } from '@nestjs/config';
import { join } from 'path';

export default registerAs('app', () => ({
  name: process.env.APP_NAME ?? 'Certificate Manager API',
  env: process.env.APP_ENV ?? 'development',
  port: parseInt(process.env.APP_PORT ?? '3000', 10),
  url: process.env.APP_URL ?? 'http://localhost:3000',
  timezone: process.env.APP_TIMEZONE ?? 'America/Bogota',
  storagePath: process.env.STORAGE_PATH ?? join(process.cwd(), 'storage', 'app'),
  swagger: {
    enabled: process.env.SWAGGER_ENABLED !== 'false',
  },
}));
