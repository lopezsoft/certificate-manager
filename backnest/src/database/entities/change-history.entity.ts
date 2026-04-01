import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from 'typeorm';
import { CertificateRequest } from './certificate-request.entity';
import { User } from './user.entity';

@Entity('change_histories')
export class ChangeHistory {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'certificate_request_id' })
  certificateRequestId: number;

  @Column({ name: 'user_id', nullable: true })
  userId: number;

  @Column({ name: 'user_of_change', nullable: true })
  userOfChange: string;

  @Column({ length: 50, nullable: true })
  status: string;

  @Column({ type: 'text', nullable: true })
  comments: string;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;

  @ManyToOne(() => CertificateRequest, (cr) => cr.history, { nullable: true })
  @JoinColumn({ name: 'certificate_request_id' })
  certificateRequest: CertificateRequest;

  @ManyToOne(() => User, { nullable: true, eager: true })
  @JoinColumn({ name: 'user_id' })
  user: User;
}
