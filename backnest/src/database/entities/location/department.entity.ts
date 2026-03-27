import {
  Column,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { Country } from './country.entity';
import { City } from './city.entity';

@Entity('departments')
export class Department {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'country_id' })
  countryId: number;

  @Column({ name: 'name_department', length: 120 })
  nameDepartment: string;

  @Column({ length: 20, nullable: true })
  code: string;

  @Column({ length: 10, nullable: true })
  abbreviation: string;

  @ManyToOne(() => Country, (c) => c.departments, { nullable: true })
  @JoinColumn({ name: 'country_id' })
  country: Country;

  @OneToMany(() => City, (city) => city.department)
  cities: City[];
}
