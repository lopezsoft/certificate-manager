import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
} from 'typeorm';

@Entity('access_users')
export class AccessUsers {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'user_id', nullable: true })
  userId: number;

  @Column({ name: 'company_id', nullable: true })
  companyId: number;

  @Column({ length: 45, nullable: true })
  ip: string;

  @Column({ nullable: true, default: 1 })
  active: number;

  @CreateDateColumn({ name: 'created_at', nullable: true })
  createdAt: Date;
}
