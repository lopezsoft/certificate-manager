/**
 * CrudModule: operaciones CRUD genéricas de configuración (general_settings, report_header).
 */
import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { GeneralSetting } from '@database/entities/settings/general-setting.entity';
import { GeneralSettingCompany } from '@database/entities/settings/general-setting-company.entity';
import { ReportHeader } from '@database/entities/settings/report-header.entity';
import { CrudController } from './crud.controller';
import { CrudService } from './crud.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([GeneralSetting, GeneralSettingCompany, ReportHeader]),
  ],
  controllers: [CrudController],
  providers: [CrudService],
  exports: [CrudService],
})
export class CrudModule { }
