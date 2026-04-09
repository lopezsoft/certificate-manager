import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
  UnauthorizedException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { ConfigService } from '@nestjs/config';
import * as bcrypt from 'bcrypt';
import { randomBytes } from 'crypto';
import { Repository } from 'typeorm';
import { User } from '@database/entities/user.entity';
import { UserType } from '@database/entities/user-type.entity';
import { PasswordReset } from '@database/entities/password-reset.entity';
import { OAuthAccessToken } from '@database/entities/oauth-access-token.entity';
import { AccessUsers } from '@database/entities/access-users.entity';
import { BusinessUser } from '@database/entities/business-user.entity';
import { Company } from '@database/entities/company.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import { LoginDto } from '@modules/auth/dto/login.dto';
import { RegisterDto } from '@modules/auth/dto/register.dto';
import { ForgotPasswordDto } from '@modules/auth/dto/forgot-password.dto';
import { ResetPasswordDto } from '@modules/auth/dto/reset-password.dto';

/**
 * Respuesta de login con estructura idéntica a Laravel Passport.
 */
export interface LoginResponse {
  access_token: string;
  user: Partial<User>;
  expires_at: string;
  message: string;
}

@Injectable()
export class AuthService {
  private readonly CONTEXT = 'AuthService';
  /** Días de expiración para el token OAuth (default: 90 días, como Passport) */
  private readonly tokenExpirationDays: number;

  constructor(
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    @InjectRepository(UserType)
    private readonly userTypeRepo: Repository<UserType>,
    @InjectRepository(PasswordReset)
    private readonly passwordResetRepo: Repository<PasswordReset>,
    @InjectRepository(OAuthAccessToken)
    private readonly oauthTokenRepo: Repository<OAuthAccessToken>,
    @InjectRepository(AccessUsers)
    private readonly accessUsersRepo: Repository<AccessUsers>,
    @InjectRepository(BusinessUser)
    private readonly businessUserRepo: Repository<BusinessUser>,
    @InjectRepository(Company)
    private readonly companyRepo: Repository<Company>,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) {
    this.tokenExpirationDays =
      this.configService.get<number>('auth.token.expiresInDays', 90);
  }

  /**
   * Devuelve todos los tipos de usuario activos.
   * GET /api/v1/auth/types
   */
  async getUserTypes(): Promise<UserType[]> {
    return this.userTypeRepo.find({ where: { active: 1 } });
  }

  /**
   * Autentica al usuario y devuelve un token OAuth Bearer (compatible Laravel Passport).
   *
   * Flujo replicado de Laravel Login.php:
   * 1. Validar credenciales (email + password)
   * 2. Verificar usuario activo
   * 3. Verificar email verificado
   * 4. Buscar empresa asociada via business_users
   * 5. Verificar empresa activa
   * 6. Crear token OAuth y almacenar en oauth_access_tokens
   * 7. Registrar acceso en access_users
   * 8. Retornar { access_token, user, expires_at, message }
   *
   * POST /api/v1/auth/login
   */
  async login(dto: LoginDto, clientIp?: string): Promise<LoginResponse> {
    // 1. Buscar usuario con password (select: false por default)
    const user = await this.userRepo
      .createQueryBuilder('user')
      .addSelect('user.password')
      .leftJoinAndSelect('user.userType', 'userType')
      .where('user.email = :email', { email: dto.email })
      .getOne();

    if (!user) {
      throw new UnauthorizedException('Credenciales inválidas.');
    }

    const passwordMatch = await bcrypt.compare(dto.password, user.password);
    if (!passwordMatch) {
      throw new UnauthorizedException('Credenciales inválidas.');
    }

    // 2. Verificar usuario activo
    if (user.active === 0) {
      throw new UnauthorizedException(
        'El usuario se encuentra inactivo. Comuníquese con el administrador.',
      );
    }

    // 3. Verificar email verificado
    if (!user.emailVerifiedAt) {
      throw new UnauthorizedException(
        'El correo electrónico no ha sido verificado. Comuníquese con el administrador.',
      );
    }

    // 4. Buscar empresa asociada via business_users
    const businessUser = await this.businessUserRepo.findOne({
      where: { userId: user.id },
    });

    if (!businessUser) {
      throw new UnauthorizedException(
        'No se ha encontrado la empresa asociada al usuario.',
      );
    }

    // 5. Verificar empresa activa
    const company = await this.companyRepo.findOne({
      where: { id: businessUser.companyId },
    });

    if (!company) {
      throw new UnauthorizedException(
        'No se ha encontrado la empresa asociada al usuario.',
      );
    }

    if ((company as any).active === 0) {
      throw new UnauthorizedException(
        'La empresa se encuentra inactiva. Comuníquese con el administrador.',
      );
    }

    // 6. Crear token OAuth — genera un ID opaco único
    const tokenId = randomBytes(40).toString('hex');
    const expiresAt = new Date();
    expiresAt.setDate(expiresAt.getDate() + this.tokenExpirationDays);

    const oauthToken = this.oauthTokenRepo.create({
      id: tokenId,
      userId: user.id,
      clientId: 1, // Default Passport client
      name: user.email,
      scopes: '[]',
      revoked: false,
      expiresAt,
    });

    await this.oauthTokenRepo.save(oauthToken);

    // 7. Registrar acceso en access_users
    const accessLog = this.accessUsersRepo.create({
      userId: user.id,
      ip: clientIp ?? undefined,
      active: 1,
    });
    await this.accessUsersRepo.save(accessLog);

    this.logger.log(`Usuario ${user.email} autenticado`, this.CONTEXT);

    // 8. Retornar respuesta con formato idéntico a Laravel
    return {
      access_token: tokenId,
      user: this.sanitizeUser(user),
      expires_at: this.formatDatetime(expiresAt),
      message: 'Bienvenido. Su sesión ha sido iniciada con éxito.',
    };
  }

  /**
   * Cierra sesión revocando el token OAuth y desactivando el registro de acceso.
   * Replica el comportamiento de Login::logout() de Laravel.
   *
   * GET /api/v1/auth/logout
   */
  async logout(
    rawToken: string | null,
    user: User,
    clientIp?: string,
  ): Promise<{ message: string }> {
    try {
      // Revocar token en oauth_access_tokens
      if (rawToken) {
        await this.oauthTokenRepo
          .createQueryBuilder()
          .update()
          .set({ revoked: true })
          .where('id = :id AND user_id = :userId', {
            id: rawToken,
            userId: user.id,
          })
          .execute();
      }

      // Desactivar registro en access_users
      await this.accessUsersRepo
        .createQueryBuilder()
        .update()
        .set({ active: 0 })
        .where('user_id = :userId', { userId: user.id })
        .andWhere('ip = :ip', { ip: clientIp ?? '' })
        .andWhere('active = 1')
        .execute();

      this.logger.log(`Usuario ${user.email} cerró sesión`, this.CONTEXT);
      return { message: 'Successfully logged out' };
    } catch (err) {
      this.logger.error(
        `Error al cerrar sesión: ${(err as Error).message}`,
        (err as Error).stack,
        this.CONTEXT,
      );
      throw err;
    }
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
        message:
          'Si el email existe, recibirás las instrucciones de recuperación.',
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

    this.logger.log(
      `Token de reset generado para: ${dto.email}`,
      this.CONTEXT,
    );

    // TODO: emitir evento para enviar el correo
    return {
      message:
        'Si el email existe, recibirás las instrucciones de recuperación.',
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

    const user = await this.userRepo.findOne({
      where: { email: dto.email },
    });
    if (!user) {
      throw new NotFoundException('Usuario no encontrado.');
    }

    user.password = await bcrypt.hash(dto.password, 10);
    await this.userRepo.save(user);
    await this.passwordResetRepo.delete({ email: dto.email });

    this.logger.log(
      `Contraseña reseteada para: ${dto.email}`,
      this.CONTEXT,
    );

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
      throw new BadRequestException(
        'El correo electrónico ya fue verificado.',
      );
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
      throw new ForbiddenException(
        'No autorizado para actualizar este perfil.',
      );
    }

    const user = await this.userRepo.findOne({ where: { id } });
    if (!user) {
      throw new NotFoundException('Usuario no encontrado.');
    }

    if (dto.email && dto.email !== user.email) {
      const existing = await this.userRepo.findOne({
        where: { email: dto.email },
      });
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

  /**
   * Extrae el token Bearer raw del header Authorization.
   */
  extractTokenFromHeader(authHeader?: string): string | null {
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return null;
    }
    return authHeader.substring(7);
  }

  /**
   * Sanitiza el objeto usuario removiendo campos sensibles
   * y añadiendo computed fields como Laravel.
   */
  private sanitizeUser(user: User): Partial<User> {
    const { password, rememberToken, ...safe } = user as User & {
      password: string;
      rememberToken: string;
    };
    return {
      ...safe,
      name: user.name,
      avatarUrl: user.avatarUrl,
    } as Partial<User>;
  }

  /**
   * Formatea fecha a string YYYY-MM-DD HH:mm:ss (formato Laravel Carbon).
   */
  private formatDatetime(date: Date): string {
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
      date.getDate(),
    )} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(
      date.getSeconds(),
    )}`;
  }
}
