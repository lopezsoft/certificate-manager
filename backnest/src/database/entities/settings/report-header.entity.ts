import {
  Column,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { Company } from '../company.entity';

@Entity('reports_header')
export class ReportHeader {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'company_id' })
  companyId: number;

  @Column({ type: 'text', nullable: true })
  line1: string;

  @Column({ type: 'text', nullable: true })
  line2: string;

  @Column({ type: 'text', nullable: true })
  foot: string;

  @Column({ type: 'text', nullable: true })
  image: string;

  @Column({ length: 50, nullable: true })
  mime: string;

  @ManyToOne(() => Company, { nullable: true })
  @JoinColumn({ name: 'company_id' })
  company: Company;
}
