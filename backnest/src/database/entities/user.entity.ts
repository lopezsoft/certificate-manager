import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from 'typeorm';
import { UserType } from './user-type.entity';

@Entity('users')
export class User {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 255 })
  email: string;

  @Column({ select: false })
  password: string;

  @Column({ name: 'type_id', nullable: true })
  typeId: number;

  @Column({ name: 'first_name', length: 100, nullable: true })
  firstName: string;

  @Column({ name: 'last_name', length: 100, nullable: true })
  lastName: string;

  @Column({ nullable: true })
  avatar: string;

  @Column({ default: 0 })
  active: number;

  @Column({ name: 'company_id', nullable: true })
  companyId: number;

  @Column({ name: 'remember_token', nullable: true, select: false })
  rememberToken: string;

  @Column({ name: 'email_verified_at', type: 'timestamp', nullable: true })
  emailVerifiedAt: Date;

  @CreateDateColumn({ name: 'created_at', select: false })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at', select: false })
  updatedAt: Date;

  // Appended computed: name, avatarUrl
  get name(): string {
    return `${this.firstName ?? ''} ${this.lastName ?? ''}`.trim();
  }

  get avatarUrl(): string | null {
    return this.avatar ? `/storage/${this.avatar}` : null;
  }

  @ManyToOne(() => UserType, { nullable: true, eager: false })
  @JoinColumn({ name: 'type_id' })
  userType: UserType;
}
