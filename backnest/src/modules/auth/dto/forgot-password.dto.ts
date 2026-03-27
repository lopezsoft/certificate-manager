import { IsEmail, IsNotEmpty } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class ForgotPasswordDto {
  @ApiProperty({ example: 'user@example.com' })
  @IsEmail({}, { message: 'El email no es válido.' })
  @IsNotEmpty({ message: 'El email es requerido.' })
  email: string;
}
