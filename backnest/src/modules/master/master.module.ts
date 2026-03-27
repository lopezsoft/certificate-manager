import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { IdentityDocument } from '@database/entities/identity-document.entity';
import { TypeOrganization } from '@database/entities/type-organization.entity';
import { UserType } from '@database/entities/user-type.entity';
import { Language } from '@database/entities/language.entity';
import { MasterController } from './master.controller';
import { MasterService } from './master.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      IdentityDocument,
      TypeOrganization,
      UserType,
      Language,
    ]),
  ],
  controllers: [MasterController],
  providers: [MasterService],
  exports: [MasterService],
})
export class MasterModule { }
