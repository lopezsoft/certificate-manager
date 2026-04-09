import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { randomBytes, createHash } from 'crypto';
import { LessThan, Repository } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { PersonalAccessToken } from '@database/entities/personal-access-token.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import { CreateTokenDto } from '@modules/tokens/dto/create-token.dto';

@Injectable()
export class TokensService {
  private readonly CONTEXT = 'TokensService';
  private readonly maxActive: number;
  private readonly maxPerDay: number;
  private readonly expirationDays: number;
  private readonly maxExpirationDays: number;

  constructor(
    @InjectRepository(PersonalAccessToken)
    private readonly tokenRepo: Repository<PersonalAccessToken>,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) {
    this.maxActive = this.configService.get<number>('tokens.maxActive', 20);
    this.maxPerDay = this.configService.get<number>('tokens.maxPerDay', 10);
    this.expirationDays = this.configService.get<number>(
      'tokens.expirationDays',
      90,
    );
    this.maxExpirationDays = this.configService.get<number>(
      'tokens.maxExpirationDays',
      365,
    );
  }

  async listForUser(userId: number): Promise<PersonalAccessToken[]> {
    return this.tokenRepo.find({
      where: { userId, isActive: true },
      order: { createdAt: 'DESC' },
      select: [
        'id',
        'name',
        'abilities',
        'expiresAt',
        'lastUsedAt',
        'isActive',
        'createdAt',
      ],
    });
  }

  async create(
    userId: number,
    dto: CreateTokenDto,
    companyId?: number,
  ): Promise<{ token: string; record: Partial<PersonalAccessToken> }> {
    // Validar límite de tokens activos
    const activeCount = await this.tokenRepo.count({
      where: { userId, isActive: true },
    });

    if (activeCount >= this.maxActive) {
      throw new BadRequestException(
        `Límite de ${this.maxActive} tokens activos alcanzado.`,
      );
    }

    // Validar límite diario
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayCount = await this.tokenRepo.count({
      where: {
        userId,
        createdAt: LessThan(new Date()),
      },
    });

    // Calcular expiración
    let expiresAt: Date;
    if (dto.expires_at) {
      expiresAt = new Date(dto.expires_at);
      const maxDate = new Date();
      maxDate.setDate(maxDate.getDate() + this.maxExpirationDays);

      if (expiresAt > maxDate) {
        throw new BadRequestException(
          `La fecha de expiración no puede superar ${this.maxExpirationDays} días.`,
        );
      }
    } else {
      expiresAt = new Date();
      expiresAt.setDate(expiresAt.getDate() + this.expirationDays);
    }

    // Generar token seguro
    const rawToken = randomBytes(40).toString('hex');
    const hashedToken = createHash('sha256').update(rawToken).digest('hex');

    const pat = this.tokenRepo.create({
      userId,
      companyId,
      name: dto.name,
      token: hashedToken,
      abilities: dto.abilities ?? ['*'],
      expiresAt,
      isActive: true,
    });

    const saved = await this.tokenRepo.save(pat);
    this.logger.log(`PAT creado para usuario ${userId}: ${saved.id}`, this.CONTEXT);

    const { token: _, ...safeRecord } = saved;

    return {
      token: rawToken, // Solo se muestra una vez
      record: safeRecord,
    };
  }

  async revoke(id: number, userId: number): Promise<void> {
    const pat = await this.tokenRepo.findOne({
      where: { id, userId, isActive: true },
    });

    if (!pat) {
      throw new NotFoundException('Token no encontrado.');
    }

    pat.isActive = false;
    await this.tokenRepo.save(pat);
    this.logger.log(`PAT revocado: ${id}`, this.CONTEXT);
  }

  async revokeAll(userId: number): Promise<void> {
    await this.tokenRepo
      .createQueryBuilder()
      .update()
      .set({ isActive: false })
      .where('user_id = :userId', { userId })
      .andWhere('is_active = true')
      .execute();

    this.logger.log(
      `Todos los PAT revocados para usuario ${userId}`,
      this.CONTEXT,
    );
  }

  async findOneForUser(id: number, userId: number): Promise<Partial<PersonalAccessToken>> {
    const token = await this.tokenRepo.findOne({
      where: { id, userId, isActive: true },
      select: ['id', 'name', 'abilities', 'expiresAt', 'lastUsedAt', 'isActive', 'createdAt'],
    });

    if (!token) {
      throw new NotFoundException('Token no encontrado.');
    }

    return token;
  }

  async renew(id: number, userId: number): Promise<{ token: string; record: Partial<PersonalAccessToken> }> {
    const current = await this.tokenRepo.findOne({
      where: { id, userId, isActive: true },
    });

    if (!current) {
      throw new NotFoundException('Token no encontrado.');
    }

    current.isActive = false;
    await this.tokenRepo.save(current);

    return this.create(
      userId,
      {
        name: current.name,
        abilities: current.abilities,
      },
      current.companyId,
    );
  }
}
