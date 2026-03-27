import { Controller, Get, Param, ParseIntPipe, Query } from '@nestjs/common';
import { ApiOperation, ApiTags } from '@nestjs/swagger';
import { LocationsService } from './locations.service';

@ApiTags('Locations')
@Controller()
export class LocationsController {
  constructor(private readonly locationsService: LocationsService) { }

  /** GET /api/v1/countries */
  @Get('countries')
  @ApiOperation({ summary: 'Listar países' })
  async countries() {
    const data = await this.locationsService.getCountries();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/departments */
  @Get('departments')
  @ApiOperation({ summary: 'Listar departamentos' })
  async departments() {
    const data = await this.locationsService.getDepartments();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/cities?query=...&code=... */
  @Get('cities')
  @ApiOperation({ summary: 'Listar ciudades (filtro opcional por query/code)' })
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
  async postalCodes(@Param('cityId', ParseIntPipe) cityId: number) {
    const data = await this.locationsService.getPostalCodesByCity(cityId);
    return { dataRecords: { data } };
  }
}
