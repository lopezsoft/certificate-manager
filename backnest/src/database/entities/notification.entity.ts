import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
} from 'typeorm';

@Entity('notifications')
export class Notification {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'varchar', length: 255 })
  type: string;

  // Tipo del modelo notificable (ej: 'User')
  @Column({ name: 'notifiable_type', length: 255 })
  notifiableType: string;

  // ID del modelo notificable
  @Column({ name: 'notifiable_id', type: 'bigint' })
  notifiableId: number;

  @Column({ type: 'jsonb' })
  data: Record<string, any>;

  @Column({ name: 'read_at', type: 'timestamp', nullable: true })
  readAt: Date;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @Column({ name: 'updated_at', type: 'timestamp', nullable: true })
  updatedAt: Date;
}
