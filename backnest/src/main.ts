import { NestFactory } from '@nestjs/core';
import { FastifyAdapter, NestFastifyApplication } from '@nestjs/platform-fastify';
import { ValidationPipe } from '@nestjs/common';
import { SwaggerModule, DocumentBuilder } from '@nestjs/swagger';
import { ConfigService } from '@nestjs/config';
import fastifyMultipart from '@fastify/multipart';
import fastifyHelmet from '@fastify/helmet';
import fastifyRateLimit from '@fastify/rate-limit';
import { AppModule } from './app.module';
import { LaravelExceptionFilter } from './common/filters/laravel-exception.filter';
import { LaravelResponseInterceptor } from './common/interceptors/laravel-response.interceptor';
import { SmartLoggerService } from './shared/logger/smart-logger.service';

async function bootstrap(): Promise<void> {
  const app = await NestFactory.create<NestFastifyApplication>(
    AppModule,
    new FastifyAdapter({ logger: false }),
  );

  const configService = app.get(ConfigService);
  const logger = app.get(SmartLoggerService);

  // Global prefix
  app.setGlobalPrefix('api/v1');

  // Security
  await app.register(fastifyHelmet as any, {
    contentSecurityPolicy: false,
  });

  // Multipart (file uploads) — 2MB limit igual que Laravel
  await app.register(fastifyMultipart as any, {
    limits: {
      fileSize: 2 * 1024 * 1024, // 2 MB
      files: 6,
    },
    attachFieldsToBody: false,
  });

  // CORS — replicar config/cors.php de Laravel
  app.enableCors({
    origin: '*',
    methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
    allowedHeaders: ['Content-Type', 'Authorization', 'Accept'],
    credentials: false,
  });

  // Global pipes — class-validator + transform
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      transform: true,
      forbidNonWhitelisted: true,
      transformOptions: {
        enableImplicitConversion: true,
      },
    }),
  );

  // Global exception filter — traduce errores al formato Laravel
  app.useGlobalFilters(new LaravelExceptionFilter());

  // Global interceptor — transforma respuestas al formato Laravel
  app.useGlobalInterceptors(new LaravelResponseInterceptor());

  // Swagger — solo en desarrollo
  if (configService.get<string>('app.swagger.enabled') !== 'false') {
    const swaggerConfig = new DocumentBuilder()
      .setTitle('Certificate Manager API')
      .setDescription('API para gestión de certificados digitales')
      .setVersion('1.0.0')
      .addBearerAuth(
        { type: 'http', scheme: 'bearer', bearerFormat: 'JWT' },
        'bearerAuth',
      )
      .addTag('Autenticación')
      .addTag('Perfil')
      .addTag('Empresas')
      .addTag('Solicitudes de Certificado')
      .addTag('Archivos')
      .addTag('Notificaciones')
      .addTag('Webhooks')
      .addTag('Tokens PAT')
      .addTag('Localización')
      .addTag('Datos Maestros')
      .addTag('Consumo')
      .addTag('Reportes')
      .addTag('CRUD Genérico')
      .addTag('Admin')
      .build();

    const document = SwaggerModule.createDocument(app, swaggerConfig);
    SwaggerModule.setup('api/docs', app, document);
  }

  const port = configService.get<number>('app.port') ?? 3000;
  await app.listen(port, '0.0.0.0');

  logger.log(`Certificate Manager API running on port ${port}`, 'Bootstrap');
  logger.log(`API prefix: /api/v1`, 'Bootstrap');
}

bootstrap();
