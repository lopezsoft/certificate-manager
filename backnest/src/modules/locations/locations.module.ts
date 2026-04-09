import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { Country } from '@database/entities/location/country.entity';
import { Department } from '@database/entities/location/department.entity';
import { City } from '@database/entities/location/city.entity';
import { PostalCode } from '@database/entities/location/postal-code.entity';
import { LocationsController } from '@modules/locations/locations.controller';
import { LocationsService } from '@modules/locations/locations.service';

@Module({
  imports: [TypeOrmModule.forFeature([Country, Department, City, PostalCode])],
  controllers: [LocationsController],
  providers: [LocationsService],
  exports: [LocationsService],
})
export class LocationsModule { }
