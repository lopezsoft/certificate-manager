import {
  IsArray,
  IsBoolean,
  IsNotEmpty,
  IsOptional,
  IsString,
  IsUrl,
  MaxLength,
} from 'class-validator';
import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';

export class CreateWebhookEndpointDto {
  @ApiProperty({ example: 'https://myapp.com/webhook' })
  @IsUrl({}, { message: 'La URL no es válida.' })
  @IsNotEmpty()
  url: string;

  @ApiProperty({ example: 'mysecret123' })
  @IsString()
  @IsNotEmpty()
  @MaxLength(255)
  secret: string;

  @ApiPropertyOptional({ example: ['certificate.created', 'certificate.approved'] })
  @IsOptional()
  @IsArray()
  events?: string[];

  @ApiPropertyOptional()
  @IsOptional()
  @IsBoolean()
  is_active?: boolean;

  @ApiPropertyOptional()
  @IsOptional()
  description?: string;
}
