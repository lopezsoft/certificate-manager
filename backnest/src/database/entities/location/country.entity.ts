import { Column, Entity, OneToMany, PrimaryGeneratedColumn } from 'typeorm';
import { Department } from './department.entity';

@Entity('countries')
export class Country {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'abbreviation_a2', length: 5, nullable: true })
  abbreviationA2: string;

  @Column({ name: 'country_name', length: 120 })
  countryName: string;

  @Column({ name: 'phone_code', length: 10, nullable: true })
  phoneCode: string;

  @OneToMany(() => Department, (d) => d.country)
  departments: Department[];
}
