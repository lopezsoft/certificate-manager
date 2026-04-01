import {
  Column,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { City } from './city.entity';

@Entity('postal_codes')
export class PostalCode {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'city_id' })
  cityId: number;

  @Column({ length: 20 })
  code: string;

  @ManyToOne(() => City, (c) => c.postalCodes, { nullable: true })
  @JoinColumn({ name: 'city_id' })
  city: City;
}
