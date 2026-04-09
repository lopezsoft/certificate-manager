import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Company } from '@database/entities/company.entity';
import { GeneralSettingCompany } from '@database/entities/settings/general-setting-company.entity';
import { PaginationQueryDto } from '@common/dto/pagination-query.dto';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import { CreateCompanyDto } from '@modules/companies/dto/create-company.dto';
import { UpdateCompanyDto } from '@modules/companies/dto/update-company.dto';

@Injectable()
export class CompaniesService {
  private readonly CONTEXT = 'CompaniesService';

  constructor(
    @InjectRepository(Company)
    private readonly companyRepo: Repository<Company>,
    @InjectRepository(GeneralSettingCompany)
    private readonly settingCompanyRepo: Repository<GeneralSettingCompany>,
    private readonly logger: SmartLoggerService,
  ) { }

  async findAll(query: PaginationQueryDto): Promise<{
    __paginated: true;
    items: Company[];
    meta: {
      currentPage: number;
      totalPages: number;
      itemsPerPage: number;
      totalItems: number;
    };
  }> {
    const page = query.page ?? 1;
    const limit = query.limit ?? 15;
    const skip = (page - 1) * limit;

    const qb = this.companyRepo
      .createQueryBuilder('company')
      .leftJoinAndSelect('company.country', 'country')
      .leftJoinAndSelect('company.city', 'city')
      .leftJoinAndSelect('company.identityDocument', 'identityDocument')
      .leftJoinAndSelect('company.typeOrganization', 'typeOrganization')
      .orderBy('company.id', 'DESC')
      .skip(skip)
      .take(limit);

    if (query.query) {
      qb.andWhere('company.company_name ILIKE :q', {
        q: `%${query.query}%`,
      });
    }

    const [items, totalItems] = await qb.getManyAndCount();

    return {
      __paginated: true,
      items,
      meta: {
        currentPage: page,
        totalPages: Math.ceil(totalItems / limit),
        itemsPerPage: limit,
        totalItems,
      },
    };
  }

  async findOne(id: number): Promise<Company> {
    const company = await this.companyRepo.findOne({
      where: { id },
      relations: ['country', 'city', 'identityDocument', 'typeOrganization'],
    });

    if (!company) {
      throw new NotFoundException('Empresa no encontrada.');
    }

    return company;
  }

  async create(dto: CreateCompanyDto): Promise<Company> {
    const company = this.companyRepo.create({
      companyName: dto.company_name,
      dni: dto.nit,
      phone: dto.phone,
      email: dto.email,
      address: dto.address,
      cityId: dto.city_id,
      countryId: dto.country_id,
      identityDocumentId: dto.identity_document_id,
      typeOrganizationId: dto.type_organization_id,
    });

    const saved = await this.companyRepo.save(company);
    this.logger.log(`Empresa creada: ${saved.id}`, this.CONTEXT);
    return saved;
  }

  async update(id: number, dto: UpdateCompanyDto): Promise<Company> {
    const company = await this.findOne(id);

    Object.assign(company, {
      ...(dto.company_name !== undefined && { companyName: dto.company_name }),
      ...(dto.nit !== undefined && { dni: dto.nit }),
      ...(dto.phone !== undefined && { phone: dto.phone }),
      ...(dto.email !== undefined && { email: dto.email }),
      ...(dto.address !== undefined && { address: dto.address }),
      ...(dto.city_id !== undefined && { cityId: dto.city_id }),
      ...(dto.country_id !== undefined && { countryId: dto.country_id }),
      ...(dto.identity_document_id !== undefined && {
        identityDocumentId: dto.identity_document_id,
      }),
      ...(dto.type_organization_id !== undefined && {
        typeOrganizationId: dto.type_organization_id,
      }),
    });

    const saved = await this.companyRepo.save(company);
    this.logger.log(`Empresa actualizada: ${id}`, this.CONTEXT);
    return saved;
  }

  async remove(id: number): Promise<void> {
    await this.findOne(id);
    await this.companyRepo.delete(id);
    this.logger.log(`Empresa eliminada: ${id}`, this.CONTEXT);
  }

  async getCompanySettings(companyId: number): Promise<GeneralSettingCompany[]> {
    return this.settingCompanyRepo.find({
      where: { companyId },
      order: { id: 'ASC' },
    });
  }

  async updateCompanySettings(
    companyId: number,
    payload: {
      settings?: Array<{
        general_setting_id: number;
        value?: string;
        is_active?: boolean;
      }>;
    },
  ): Promise<GeneralSettingCompany[]> {
    const settings = payload.settings ?? [];
    if (!Array.isArray(settings) || settings.length === 0) {
      throw new BadRequestException('No se recibieron configuraciones para actualizar.');
    }

    const entities = await Promise.all(
      settings.map(async (item) => {
        let record = await this.settingCompanyRepo.findOne({
          where: {
            companyId,
            generalSettingId: item.general_setting_id,
          },
        });

        if (!record) {
          record = this.settingCompanyRepo.create({
            companyId,
            generalSettingId: item.general_setting_id,
            value: item.value,
            isActive: item.is_active ?? true,
          });
        } else {
          if (item.value !== undefined) record.value = item.value;
          if (item.is_active !== undefined) record.isActive = item.is_active;
        }

        return this.settingCompanyRepo.save(record);
      }),
    );

    this.logger.log(
      `Configuraciones de empresa actualizadas: companyId=${companyId}, total=${entities.length}`,
      this.CONTEXT,
    );

    return entities;
  }
}
