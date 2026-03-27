import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { IdentityDocument } from '@database/entities/identity-document.entity';
import { TypeOrganization } from '@database/entities/type-organization.entity';
import { UserType } from '@database/entities/user-type.entity';
import { Language } from '@database/entities/language.entity';

@Injectable()
export class MasterService {
  constructor(
    @InjectRepository(IdentityDocument)
    private readonly identityDocumentRepo: Repository<IdentityDocument>,
    @InjectRepository(TypeOrganization)
    private readonly typeOrganizationRepo: Repository<TypeOrganization>,
    @InjectRepository(UserType)
    private readonly userTypeRepo: Repository<UserType>,
    @InjectRepository(Language)
    private readonly languageRepo: Repository<Language>,
  ) { }

  async getIdentityDocuments(): Promise<IdentityDocument[]> {
    return this.identityDocumentRepo.find({ order: { documentName: 'ASC' } });
  }

  async getTypeOrganizations(): Promise<TypeOrganization[]> {
    return this.typeOrganizationRepo.find({ order: { name: 'ASC' } });
  }

  async getUserTypes(): Promise<UserType[]> {
    return this.userTypeRepo.find({ where: { active: 1 } });
  }

  async getLanguages(): Promise<Language[]> {
    return this.languageRepo.find({
      where: { isActive: true },
      order: { name: 'ASC' },
    });
  }
}
