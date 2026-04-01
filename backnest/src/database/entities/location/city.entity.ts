import {
  Column,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { Department } from './department.entity';
import { PostalCode } from './postal-code.entity';

@Entity('cities')
export class City {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'department_id' })
  departmentId: number;

  @Column({ name: 'city_name', length: 120 })
  cityName: string;

  @Column({ length: 20, nullable: true })
  code: string;

  @ManyToOne(() => Department, (d) => d.cities, { nullable: true, eager: true })
  @JoinColumn({ name: 'department_id' })
  department: Department;

  @OneToMany(() => PostalCode, (p) => p.city)
  postalCodes: PostalCode[];
}
