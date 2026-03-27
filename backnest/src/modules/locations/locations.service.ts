import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Country } from '@database/entities/location/country.entity';
import { Department } from '@database/entities/location/department.entity';
import { City } from '@database/entities/location/city.entity';
import { PostalCode } from '@database/entities/location/postal-code.entity';

@Injectable()
export class LocationsService {
  constructor(
    @InjectRepository(Country)
    private readonly countryRepo: Repository<Country>,
    @InjectRepository(Department)
    private readonly departmentRepo: Repository<Department>,
    @InjectRepository(City)
    private readonly cityRepo: Repository<City>,
    @InjectRepository(PostalCode)
    private readonly postalCodeRepo: Repository<PostalCode>,
  ) { }

  async getCountries(): Promise<Country[]> {
    return this.countryRepo.find({ order: { countryName: 'ASC' } });
  }

  async getDepartments(): Promise<Department[]> {
    return this.departmentRepo.find({
      order: { nameDepartment: 'ASC' },
    });
  }

  async getCities(query?: string, code?: string): Promise<City[]> {
    const qb = this.cityRepo
      .createQueryBuilder('city')
      .leftJoinAndSelect('city.postalCodes', 'postalCode')
      .orderBy('city.cityName', 'ASC');

    if (query) {
      qb.andWhere('(city.cityName ILIKE :q OR city.code ILIKE :q)', {
        q: `%${query}%`,
      });
    }

    if (code) {
      qb.andWhere('city.code = :code', { code });
    }

    return qb.getMany();
  }

  async getPostalCodesByCity(cityId: number): Promise<PostalCode[]> {
    return this.postalCodeRepo.find({
      where: { cityId },
      order: { code: 'ASC' },
    });
  }
}
