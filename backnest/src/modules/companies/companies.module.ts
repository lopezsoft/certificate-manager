import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { Company } from '@database/entities/company.entity';
import { GeneralSettingCompany } from '@database/entities/settings/general-setting-company.entity';
import { CompaniesController } from '@modules/companies/companies.controller';
import { CompaniesService } from '@modules/companies/companies.service';

@Module({
  imports: [TypeOrmModule.forFeature([Company, GeneralSettingCompany])],
  controllers: [CompaniesController],
  providers: [CompaniesService],
  exports: [CompaniesService],
})
export class CompaniesModule { }
