import { Column, Entity, PrimaryGeneratedColumn } from 'typeorm';

@Entity('access_users')
export class AccessUsers {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'user_id', nullable: true })
  userId: number;

  @Column({ name: 'company_id', nullable: true })
  companyId: number;

  @Column({ nullable: true })
  active: number;
}
