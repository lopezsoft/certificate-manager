import { NestFactory } from '@nestjs/core';
import { FastifyAdapter, NestFastifyApplication } from '@nestjs/platform-fastify';
import { ValidationPipe } from '@nestjs/common';
import { SwaggerModule, DocumentBuilder } from '@nestjs/swagger';
import { ConfigService } from '@nestjs/config';
import fastifyMultipart from '@fastify/multipart';
import fastifyHelmet from '@fastify/helmet';
import fastifyRateLimit from '@fastify/rate-limit';
import fastifyStatic from '@fastify/static';
import { join } from 'path';
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

  // Static files — replica los symlinks de Laravel:
  //   public/attachments → storage/app/attachments
  //   public/pdf         → storage/app/pdf
  const storagePath = configService.get<string>('app.storagePath')
    ?? join(process.cwd(), 'storage', 'app');

  await app.register(fastifyStatic as any, {
    root: join(storagePath, 'attachments'),
    prefix: '/attachments/',
    decorateReply: true,
  });

  await app.register(fastifyStatic as any, {
    root: join(storagePath, 'pdf'),
    prefix: '/pdf/',
    decorateReply: false,
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
      .setDescription(
        'API REST para la gestión integral de certificados digitales.\n\n' +
          '## Autenticación\n' +
          'La mayoría de endpoints requieren un token **Bearer JWT** en el header `Authorization`.\n' +
          'Obtén tu token mediante `POST /api/v1/auth/login`.\n\n' +
          '## Formato de respuesta\n' +
          'Todas las respuestas siguen el formato estándar:\n' +
          '```json\n{ "dataRecords": { "data": ... } }\n```\n\n' +
          '## Rate Limiting\n' +
          'Algunos endpoints sensibles tienen limitación de solicitudes por ventana de tiempo.',
      )
      .setVersion('1.0.0')
      .setContact('LopezSoft', '', '')
      .setLicense('MIT', '')
      .addBearerAuth({
        type: 'http',
        scheme: 'bearer',
        bearerFormat: 'JWT',
        description: 'Ingresa tu token JWT obtenido del endpoint de login',
      })
      .addTag('Auth', 'Autenticación, registro, recuperación de contraseña y gestión de sesión')
      .addTag('Users', 'Gestión CRUD de usuarios del sistema')
      .addTag('Companies', 'Gestión de empresas y sus configuraciones')
      .addTag('Certificates', 'Solicitudes de certificados digitales y cambios de estado')
      .addTag('Files', 'Carga, consulta y eliminación de archivos asociados a solicitudes')
      .addTag(
        'Notifications',
        'Notificaciones del usuario y alertas de vencimiento de certificados',
      )
      .addTag('Webhooks', 'Endpoints de webhook, suscripción a eventos y rotación de secretos')
      .addTag('Tokens', 'Personal Access Tokens (PAT) — creación, renovación y revocación')
      .addTag(
        'Locations',
        'Datos de localización: países, departamentos, ciudades y códigos postales',
      )
      .addTag('Master', 'Datos maestros: tipos de documento, organización, usuario e idiomas')
      .addTag('Consume', 'Consulta de consumo agregado por año y mes')
      .addTag(
        'Settings',
        'Configuraciones globales, de empresa, encabezados de reportes y CRUD genérico',
      )
      .addTag('AI', 'Análisis de documentos mediante OCR e Inteligencia Artificial')
      .build();

    const document = SwaggerModule.createDocument(app, swaggerConfig);
    SwaggerModule.setup('api/docs', app, document, {
      customSiteTitle: 'Certificate Manager API Docs',
      swaggerOptions: {
        persistAuthorization: true,
        docExpansion: 'none',
        filter: true,
        tagsSorter: 'alpha',
        operationsSorter: 'alpha',
      },
    });
  }

  const port = configService.get<number>('app.port') ?? 3000;
  await app.listen(port, '0.0.0.0');

  logger.log(`Certificate Manager API running on port ${port}`, 'Bootstrap');
  logger.log(`API prefix: /api/v1`, 'Bootstrap');
}

bootstrap();
