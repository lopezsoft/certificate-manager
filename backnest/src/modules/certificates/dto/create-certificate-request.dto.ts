import {
  IsDateString,
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';
import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';

export class CreateCertificateRequestDto {
  @ApiProperty()
  @IsNumber()
  @Type(() => Number)
  company_id: number;

  @ApiPropertyOptional()
  @IsOptional()
  @IsNumber()
  @Type(() => Number)
  city_id?: number;

  @ApiPropertyOptional()
  @IsOptional()
  @IsNumber()
  @Type(() => Number)
  identity_document_id?: number;

  @ApiPropertyOptional()
  @IsOptional()
  @IsNumber()
  @Type(() => Number)
  type_organization_id?: number;

  @ApiPropertyOptional()
  @IsOptional()
  @MaxLength(120)
  company_name?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @MaxLength(30)
  dni?: string;

  @ApiPropertyOptional()
  @IsOptional()
  dv?: number;

  @ApiPropertyOptional()
  @IsOptional()
  @MaxLength(255)
  address?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @MaxLength(30)
  document_number?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @MaxLength(30)
  phone?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @MaxLength(30)
  mobile?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @MaxLength(120)
  legal_representative?: string;

  @ApiPropertyOptional()
  @IsOptional()
  info?: string;

  @ApiPropertyOptional({ default: 'DRAFT' })
  @IsOptional()
  request_status?: string;

  @ApiPropertyOptional()
  @IsOptional()
  postal_code?: string;

  @ApiPropertyOptional()
  @IsOptional()
  life?: number;

  @ApiPropertyOptional()
  @IsOptional()
  document_type?: string;

  @ApiPropertyOptional()
  @IsOptional()
  expiration_date?: string;
}
