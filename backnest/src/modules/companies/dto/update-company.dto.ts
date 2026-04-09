import { PartialType } from '@nestjs/swagger';
import { CreateCompanyDto } from '@modules/companies/dto/create-company.dto';

export class UpdateCompanyDto extends PartialType(CreateCompanyDto) {}
