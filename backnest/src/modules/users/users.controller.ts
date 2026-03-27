import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  HttpStatus,
  Param,
  ParseIntPipe,
  Post,
  Put,
  Query,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { PaginationQueryDto } from '@common/dto/pagination-query.dto';
import { UsersService } from './users.service';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';

@ApiTags('Users')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('users')
export class UsersController {
  constructor(private readonly usersService: UsersService) { }

  @Get()
  @ApiOperation({ summary: 'Listar usuarios (paginado)' })
  async index(@Query() query: PaginationQueryDto) {
    return this.usersService.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: 'Obtener usuario por ID' })
  async show(@Param('id', ParseIntPipe) id: number) {
    const data = await this.usersService.findOne(id);
    return { dataRecords: { data } };
  }

  @Post()
  @ApiOperation({ summary: 'Crear usuario' })
  async store(@Body() dto: CreateUserDto) {
    const data = await this.usersService.create(dto);
    return { message: 'Recurso creado exitosamente.', dataRecords: { data } };
  }

  @Put(':id')
  @ApiOperation({ summary: 'Actualizar usuario' })
  async update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateUserDto,
  ) {
    const data = await this.usersService.update(id, dto);
    return { dataRecords: { data } };
  }

  @Delete(':id')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Eliminar usuario' })
  async destroy(@Param('id', ParseIntPipe) id: number) {
    await this.usersService.remove(id);
    return { message: 'Recurso eliminado exitosamente.' };
  }
}
