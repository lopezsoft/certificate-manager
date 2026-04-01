import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import * as bcrypt from 'bcrypt';
import { Repository } from 'typeorm';
import { User } from '@database/entities/user.entity';
import { PaginationQueryDto } from '@common/dto/pagination-query.dto';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';

@Injectable()
export class UsersService {
  private readonly CONTEXT = 'UsersService';

  constructor(
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    private readonly logger: SmartLoggerService,
  ) { }

  async findAll(query: PaginationQueryDto) {
    const page = query.page ?? 1;
    const limit = query.limit ?? 15;
    const skip = (page - 1) * limit;

    const qb = this.userRepo
      .createQueryBuilder('u')
      .leftJoinAndSelect('u.userType', 'userType')
      .orderBy('u.id', 'DESC')
      .skip(skip)
      .take(limit);

    if (query.query) {
      qb.where(
        '(u.first_name ILIKE :q OR u.last_name ILIKE :q OR u.email ILIKE :q)',
        { q: `%${query.query}%` },
      );
    }

    const [items, totalItems] = await qb.getManyAndCount();

    return {
      __paginated: true as const,
      items: items.map((u) => this.sanitize(u)),
      meta: {
        currentPage: page,
        totalPages: Math.ceil(totalItems / limit),
        itemsPerPage: limit,
        totalItems,
      },
    };
  }

  async findOne(id: number): Promise<Partial<User>> {
    const user = await this.userRepo.findOne({
      where: { id },
      relations: ['userType'],
    });

    if (!user) throw new NotFoundException('Usuario no encontrado.');
    return this.sanitize(user);
  }

  async create(dto: CreateUserDto): Promise<Partial<User>> {
    const existing = await this.userRepo.findOne({ where: { email: dto.email } });
    if (existing) throw new BadRequestException('El email ya está en uso.');

    const user = this.userRepo.create({
      firstName: dto.first_name,
      lastName: dto.last_name,
      email: dto.email,
      password: await bcrypt.hash(dto.password, 10),
      typeId: dto.type_id ?? 2,
      active: (dto.active ?? true) ? 1 : 0,
    });

    const saved = await this.userRepo.save(user);
    this.logger.log(`Usuario creado: ${saved.id}`, this.CONTEXT);
    return this.sanitize(saved);
  }

  async update(id: number, dto: UpdateUserDto): Promise<Partial<User>> {
    const user = await this.userRepo.findOneOrFail({ where: { id } });

    if (dto.email && dto.email !== user.email) {
      const dup = await this.userRepo.findOne({ where: { email: dto.email } });
      if (dup) throw new BadRequestException('El email ya está en uso.');
      user.email = dto.email;
    }

    if (dto.first_name) user.firstName = dto.first_name;
    if (dto.last_name) user.lastName = dto.last_name;
    if (dto.type_id !== undefined) user.typeId = dto.type_id;
    if (dto.active !== undefined) user.active = dto.active ? 1 : 0;
    if (dto.password) user.password = await bcrypt.hash(dto.password, 10);

    const saved = await this.userRepo.save(user);
    this.logger.log(`Usuario actualizado: ${id}`, this.CONTEXT);
    return this.sanitize(saved);
  }

  async remove(id: number): Promise<void> {
    await this.findOne(id);
    await this.userRepo.delete(id);
    this.logger.log(`Usuario eliminado: ${id}`, this.CONTEXT);
  }

  private sanitize(user: User): Partial<User> {
    const { password, ...safe } = user as User & { password: string };
    return safe;
  }
}
