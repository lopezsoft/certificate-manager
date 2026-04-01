import { PartialType } from '@nestjs/swagger';
import { IsNotEmpty, IsOptional, IsString } from 'class-validator';
import { CreateCertificateRequestDto } from './create-certificate-request.dto';

export class UpdateCertificateRequestDto extends PartialType(
  CreateCertificateRequestDto,
) { }

export class UpdateCertificateStatusDto {
  @IsString()
  @IsNotEmpty()
  request_status: string;

  @IsOptional()
  comments?: string;
}
