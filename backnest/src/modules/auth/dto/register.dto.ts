import {
  IsEmail,
  IsNotEmpty,
  IsOptional,
  IsString,
  MinLength,
} from 'class-validator';
import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';

export class RegisterDto {
  @ApiProperty({ example: 'John' })
  @IsString()
  @IsNotEmpty({ message: 'El nombre es requerido.' })
  first_name: string;

  @ApiProperty({ example: 'Doe' })
  @IsString()
  @IsNotEmpty({ message: 'El apellido es requerido.' })
  last_name: string;

  @ApiProperty({ example: 'john@example.com' })
  @IsEmail({}, { message: 'El email no es válido.' })
  email: string;

  @ApiProperty({ example: 'secret123' })
  @IsString()
  @MinLength(8, { message: 'La contraseña debe tener al menos 8 caracteres.' })
  password: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsString()
  password_confirmation?: string;

  @ApiPropertyOptional()
  @IsOptional()
  type_id?: number;
}
