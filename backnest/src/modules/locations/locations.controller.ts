import { Controller, Get, Param, ParseIntPipe, Query } from '@nestjs/common';
import {
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiQuery,
  ApiTags,
} from '@nestjs/swagger';
import { LocationsService } from './locations.service';

@ApiTags('Locations')
@Controller()
export class LocationsController {
  constructor(private readonly locationsService: LocationsService) { }

  /** GET /api/v1/countries */
  @Get('countries')
  @ApiOperation({ summary: 'Listar países' })
  @ApiOkResponse({ description: 'Listado de países' })
  async countries() {
    const data = await this.locationsService.getCountries();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/departments */
  @Get('departments')
  @ApiOperation({ summary: 'Listar departamentos' })
  @ApiOkResponse({ description: 'Listado de departamentos' })
  async departments() {
    const data = await this.locationsService.getDepartments();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/cities?query=...&code=... */
  @Get('cities')
  @ApiOperation({ summary: 'Listar ciudades (filtro opcional por query/code)' })
  @ApiQuery({ name: 'query', required: false, type: String })
  @ApiQuery({ name: 'code', required: false, type: String })
  @ApiOkResponse({ description: 'Listado de ciudades filtrado opcionalmente' })
  async cities(
    @Query('query') query?: string,
    @Query('code') code?: string,
  ) {
    const data = await this.locationsService.getCities(query, code);
    return { dataRecords: { data } };
  }

  /** GET /api/v1/postal-codes/:cityId */
  @Get('postal-codes/:cityId')
  @ApiOperation({ summary: 'Códigos postales por ciudad' })
  @ApiParam({ name: 'cityId', type: Number })
  @ApiOkResponse({ description: 'Listado de códigos postales de la ciudad' })
  async postalCodes(@Param('cityId', ParseIntPipe) cityId: number) {
    const data = await this.locationsService.getPostalCodesByCity(cityId);
    return { dataRecords: { data } };
  }
}
