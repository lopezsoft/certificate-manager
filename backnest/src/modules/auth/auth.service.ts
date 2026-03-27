import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
  UnauthorizedException,
} from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { InjectRepository } from '@nestjs/typeorm';
import * as bcrypt from 'bcrypt';
import { randomBytes } from 'crypto';
import { MoreThan, Repository } from 'typeorm';
import { User } from '@database/entities/user.entity';
import { UserType } from '@database/entities/user-type.entity';
import { PasswordReset } from '@database/entities/password-reset.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import { LoginDto } from './dto/login.dto';
import { RegisterDto } from './dto/register.dto';
import { ForgotPasswordDto } from './dto/forgot-password.dto';
import { ResetPasswordDto } from './dto/reset-password.dto';
import { JwtPayload } from './strategies/jwt.strategy';

@Injectable()
export class AuthService {
  private readonly CONTEXT = 'AuthService';

  constructor(
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    @InjectRepository(UserType)
    private readonly userTypeRepo: Repository<UserType>,
    @InjectRepository(PasswordReset)
    private readonly passwordResetRepo: Repository<PasswordReset>,
    private readonly jwtService: JwtService,
    private readonly logger: SmartLoggerService,
  ) { }

  /**
   * Devuelve todos los tipos de usuario activos.
   * GET /api/v1/auth/types
   */
  async getUserTypes(): Promise<UserType[]> {
    return this.userTypeRepo.find({ where: { active: 1 } });
  }

  /**
   * Autentica al usuario y devuelve un JWT.
   * POST /api/v1/auth/login
   */
  async login(dto: LoginDto): Promise<{
    token: string;
    token_type: string;
    user: Partial<User>;
  }> {
    const user = await this.userRepo.findOne({
      where: { email: dto.email, active: 1 },
      relations: ['userType'],
    });

    if (!user) {
      throw new UnauthorizedException('Credenciales incorrectas.');
    }

    const passwordMatch = await bcrypt.compare(dto.password, user.password);
    if (!passwordMatch) {
      throw new UnauthorizedException('Credenciales incorrectas.');
    }

    const payload: JwtPayload = {
      sub: user.id,
      email: user.email,
      type: user.userType?.type,
    };

    const token = this.jwtService.sign(payload);
    this.logger.log(`Usuario ${user.email} autenticado`, this.CONTEXT);

    return {
      token,
      token_type: 'Bearer',
      user: this.sanitizeUser(user),
    };
  }

  /**
   * Registra un nuevo usuario.
   * POST /api/v1/auth/register
   */
  async register(dto: RegisterDto): Promise<{ message: string }> {
    const existing = await this.userRepo.findOne({
      where: { email: dto.email },
    });
    if (existing) {
      throw new BadRequestException('El email ya está en uso.');
    }

    const hashedPassword = await bcrypt.hash(dto.password, 10);

    const user = this.userRepo.create({
      email: dto.email,
      password: hashedPassword,
      firstName: dto.first_name,
      lastName: dto.last_name,
      typeId: dto.type_id ?? 2,
      active: 1,
    });

    await this.userRepo.save(user);
    this.logger.log(`Nuevo usuario registrado: ${user.email}`, this.CONTEXT);

    return { message: 'Recurso creado exitosamente.' };
  }

  /**
   * Genera token de recuperación de contraseña.
   * POST /api/v1/auth/forgot-password
   */
  async forgotPassword(dto: ForgotPasswordDto): Promise<{ message: string }> {
    const user = await this.userRepo.findOne({ where: { email: dto.email } });

    // Siempre responder igual para no revelar existencia del email
    if (!user) {
      return {
        message: 'Si el email existe, recibirás las instrucciones de recuperación.',
      };
    }

    const token = randomBytes(40).toString('hex');

    await this.passwordResetRepo.delete({ email: dto.email });
    await this.passwordResetRepo.save(
      this.passwordResetRepo.create({
        email: dto.email,
        token,
        createdAt: new Date(),
      }),
    );

    this.logger.log(`Token de reset generado para: ${dto.email}`, this.CONTEXT);

    // TODO: emitir evento para envear el correo
    return {
      message: 'Si el email existe, recibirás las instrucciones de recuperación.',
    };
  }

  /**
   * Resetea la contraseña con el token.
   * POST /api/v1/auth/reset-password
   */
  async resetPassword(dto: ResetPasswordDto): Promise<{ message: string }> {
    if (dto.password !== dto.password_confirmation) {
      throw new BadRequestException('Las contraseñas no coinciden.');
    }

    const resetRecord = await this.passwordResetRepo.findOne({
      where: { email: dto.email, token: dto.token },
    });

    if (!resetRecord) {
      throw new BadRequestException('Token inválido o expirado.');
    }

    const user = await this.userRepo.findOne({ where: { email: dto.email } });
    if (!user) {
      throw new NotFoundException('Usuario no encontrado.');
    }

    user.password = await bcrypt.hash(dto.password, 10);
    await this.userRepo.save(user);
    await this.passwordResetRepo.delete({ email: dto.email });

    this.logger.log(`Contraseña reseteada para: ${dto.email}`, this.CONTEXT);

    return { message: 'Contraseña actualizada exitosamente.' };
  }

  /**
   * Perfil del usuario autenticado.
   * GET /api/v1/auth/user
   */
  async getProfile(userId: number): Promise<Partial<User>> {
    const user = await this.userRepo.findOne({
      where: { id: userId },
      relations: ['userType'],
    });

    if (!user) {
      throw new NotFoundException('Usuario no encontrado.');
    }

    return this.sanitizeUser(user);
  }

  async verifyEmail(userId: number): Promise<{ message: string }> {
    const user = await this.userRepo.findOne({ where: { id: userId } });
    if (!user) {
      throw new NotFoundException('Usuario no encontrado.');
    }

    if (!user.emailVerifiedAt) {
      user.emailVerifiedAt = new Date();
      await this.userRepo.save(user);
    }

    return { message: 'Email verificado exitosamente.' };
  }

  async sendEmailVerificationNotification(
    userId: number,
  ): Promise<{ message: string }> {
    const user = await this.userRepo.findOne({ where: { id: userId } });
    if (!user) {
      throw new NotFoundException('Usuario no encontrado.');
    }

    return { message: 'Notificación de verificación enviada exitosamente.' };
  }

  async sendEmailVerificationNotificationByEmail(
    email: string,
  ): Promise<{ message: string }> {
    const user = await this.userRepo.findOne({ where: { email } });
    if (!user) {
      throw new NotFoundException('El usuario no existe.');
    }

    if (user.emailVerifiedAt) {
      throw new BadRequestException('El correo electrónico ya fue verificado.');
    }

    return { message: 'Se ha enviado un correo electrónico de verificación' };
  }

  async updateProfile(
    id: number,
    dto: {
      first_name?: string;
      last_name?: string;
      email?: string;
      password?: string;
    },
    currentUserId: number,
  ): Promise<Partial<User>> {
    if (id !== currentUserId) {
      throw new ForbiddenException('No autorizado para actualizar este perfil.');
    }

    const user = await this.userRepo.findOne({ where: { id } });
    if (!user) {
      throw new NotFoundException('Usuario no encontrado.');
    }

    if (dto.email && dto.email !== user.email) {
      const existing = await this.userRepo.findOne({ where: { email: dto.email } });
      if (existing && existing.id !== id) {
        throw new BadRequestException('El email ya está en uso.');
      }
      user.email = dto.email;
    }

    if (dto.first_name !== undefined) user.firstName = dto.first_name;
    if (dto.last_name !== undefined) user.lastName = dto.last_name;
    if (dto.password) user.password = await bcrypt.hash(dto.password, 10);

    const saved = await this.userRepo.save(user);
    return this.sanitizeUser(saved);
  }

  private sanitizeUser(user: User): Partial<User> {
    const { password, ...safe } = user as User & { password: string };
    return safe;
  }
}
