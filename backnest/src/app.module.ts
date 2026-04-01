import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { EventEmitterModule } from '@nestjs/event-emitter';
import { ScheduleModule } from '@nestjs/schedule';
import { BullModule } from '@nestjs/bull';

// Config namespaces
import appConfig from './config/app.config';
import authConfig from './config/auth.config';
import databaseConfig from './config/database.config';
import mailConfig from './config/mail.config';
import certificateConfig from './config/certificate.config';
import aiConfig from './config/ai.config';
import tokensConfig from './config/tokens.config';
import webhooksConfig from './config/webhooks.config';
import { databaseConfig as typeOrmConfig } from './database/database.config';

// Feature modules
import { SharedModule } from './shared/shared.module';
import { AuthModule } from './modules/auth/auth.module';
import { UsersModule } from './modules/users/users.module';
import { CompaniesModule } from './modules/companies/companies.module';
import { LocationsModule } from './modules/locations/locations.module';
import { MasterModule } from './modules/master/master.module';
import { CertificatesModule } from './modules/certificates/certificates.module';
import { ConsumeModule } from './modules/consume/consume.module';
import { CrudModule } from './modules/crud/crud.module';
import { NotificationsModule } from './modules/notifications/notifications.module';
import { TokensModule } from './modules/tokens/tokens.module';
import { WebhooksModule } from './modules/webhooks/webhooks.module';
import { AiModule } from './modules/ai/ai.module';
import { MailModule } from './modules/mail/mail.module';
import { ReportsModule } from './modules/reports/reports.module';
import { FilesModule } from './modules/files/files.module';
import { SchedulerModule } from './modules/scheduler/scheduler.module';

@Module({
  imports: [
    // Config — carga todas las variables de entorno
    ConfigModule.forRoot({
      isGlobal: true,
      load: [
        appConfig,
        authConfig,
        databaseConfig,
        mailConfig,
        certificateConfig,
        aiConfig,
        tokensConfig,
        webhooksConfig,
      ],
      envFilePath: ['.env'],
    }),

    // TypeORM — conexión a PostgreSQL
    TypeOrmModule.forRootAsync(typeOrmConfig),

    // EventEmitter2 — eventos de dominio
    EventEmitterModule.forRoot({
      wildcard: true,
      delimiter: '.',
      maxListeners: 20,
    }),

    // Schedule — cron jobs
    ScheduleModule.forRoot(),

    // Bull Queue — procesamiento asíncrono
    BullModule.forRootAsync({
      inject: [],
      useFactory: () => ({
        redis: {
          host: process.env.REDIS_HOST ?? 'localhost',
          port: parseInt(process.env.REDIS_PORT ?? '6379'),
          password: process.env.REDIS_PASSWORD || undefined,
        },
        defaultJobOptions: {
          removeOnComplete: 100,
          removeOnFail: 50,
        },
      }),
    }),

    // Feature modules
    SharedModule,
    MailModule,
    AuthModule,
    UsersModule,
    CompaniesModule,
    LocationsModule,
    MasterModule,
    CertificatesModule,
    ConsumeModule,
    CrudModule,
    NotificationsModule,
    TokensModule,
    WebhooksModule,
    AiModule,
    ReportsModule,
    FilesModule,
    SchedulerModule,
  ],
})
export class AppModule { }
