import { Controller, Get, Param, ParseIntPipe, UseGuards } from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { ConsumeService } from '@modules/consume/consume.service';

@ApiTags('Consume')
@ApiBearerAuth()
@ApiUnauthorizedResponse({ description: 'Token inválido o expirado' })
@UseGuards(JwtAuthGuard)
@Controller('consume')
export class ConsumeController {
  constructor(private readonly consumeService: ConsumeService) { }

  /** GET /api/v1/consume/:year */
  @Get(':year')
  @ApiOperation({ summary: 'Consultar consumo por año' })
  @ApiParam({ name: 'year', type: Number })
  @ApiOkResponse({ description: 'Consumo agregado por año' })
  async readByYear(@Param('year', ParseIntPipe) year: number) {
    const data = await this.consumeService.readByYear(year);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/consume/:year/:month */
  @Get(':year/:month')
  @ApiOperation({ summary: 'Consultar consumo por año y mes' })
  @ApiParam({ name: 'year', type: Number })
  @ApiParam({ name: 'month', type: Number })
  @ApiOkResponse({ description: 'Consumo agregado por año y mes' })
  async readByMonth(
    @Param('year', ParseIntPipe) year: number,
    @Param('month', ParseIntPipe) month: number,
  ) {
    const data = await this.consumeService.readByMonth(year, month);
    return { dataRecords: { data } };
  }
}
