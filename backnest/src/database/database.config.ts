import { TypeOrmModuleAsyncOptions } from '@nestjs/typeorm';
import { ConfigService } from '@nestjs/config';
import { DataSourceOptions } from 'typeorm';

// Entities
import { User } from './entities/user.entity';
import { UserType } from './entities/user-type.entity';
import { PasswordReset } from './entities/password-reset.entity';
import { AccessUsers } from './entities/access-users.entity';
import { Company } from './entities/company.entity';
import { CertificateRequest } from './entities/certificate-request.entity';
import { FileManager } from './entities/file-manager.entity';
import { ChangeHistory } from './entities/change-history.entity';
import { DocumentAnalysisResult } from './entities/document-analysis-result.entity';
import { IdentityDocument } from './entities/identity-document.entity';
import { TypeOrganization } from './entities/type-organization.entity';
import { Language } from './entities/language.entity';
import { Country } from './entities/location/country.entity';
import { Department } from './entities/location/department.entity';
import { City } from './entities/location/city.entity';
import { PostalCode } from './entities/location/postal-code.entity';
import { GeneralSetting } from './entities/settings/general-setting.entity';
import { GeneralSettingCompany } from './entities/settings/general-setting-company.entity';
import { ReportHeader } from './entities/settings/report-header.entity';
import { WebhookEndpoint } from './entities/webhook-endpoint.entity';
import { WebhookDelivery } from './entities/webhook-delivery.entity';
import { PersonalAccessToken } from './entities/personal-access-token.entity';
import { Notification } from './entities/notification.entity';

export const ALL_ENTITIES = [
  User,
  UserType,
  PasswordReset,
  AccessUsers,
  Company,
  CertificateRequest,
  FileManager,
  ChangeHistory,
  DocumentAnalysisResult,
  IdentityDocument,
  TypeOrganization,
  Language,
  Country,
  Department,
  City,
  PostalCode,
  GeneralSetting,
  GeneralSettingCompany,
  ReportHeader,
  WebhookEndpoint,
  WebhookDelivery,
  PersonalAccessToken,
  Notification,
];

export const databaseConfig: TypeOrmModuleAsyncOptions = {
  inject: [ConfigService],
  useFactory: (configService: ConfigService): DataSourceOptions => ({
    type: 'postgres',
    host: configService.get<string>('database.host'),
    port: configService.get<number>('database.port'),
    username: configService.get<string>('database.username'),
    password: configService.get<string>('database.password'),
    database: configService.get<string>('database.database'),
    entities: ALL_ENTITIES,
    synchronize: configService.get<boolean>('database.synchronize') ?? false,
    logging: configService.get<boolean>('database.logging') ?? false,
    extra: { options: "-c TimeZone='America/Bogota'" },
  }),
};
