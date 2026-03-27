import {
  IsNotEmpty,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';
import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';

export class CreateCompanyDto {
  @ApiProperty({ example: 'Mi Empresa S.A.S.' })
  @IsString()
  @IsNotEmpty()
  @MaxLength(120)
  company_name: string;

  @ApiPropertyOptional()
  @IsOptional()
  nit?: string;

  @ApiPropertyOptional()
  @IsOptional()
  phone?: string;

  @ApiPropertyOptional()
  @IsOptional()
  email?: string;

  @ApiPropertyOptional()
  @IsOptional()
  address?: string;

  @ApiPropertyOptional()
  @IsOptional()
  city_id?: number;

  @ApiPropertyOptional()
  @IsOptional()
  country_id?: number;

  @ApiPropertyOptional()
  @IsOptional()
  identity_document_id?: number;

  @ApiPropertyOptional()
  @IsOptional()
  type_organization_id?: number;

  @ApiPropertyOptional()
  @IsOptional()
  active?: boolean;
}
