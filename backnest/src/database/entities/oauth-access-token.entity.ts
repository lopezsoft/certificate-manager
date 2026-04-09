import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryColumn,
  UpdateDateColumn,
} from 'typeorm';
import { User } from './user.entity';

/**
 * Entidad que mapea la tabla `oauth_access_tokens` de Laravel Passport.
 *
 * Almacena tokens opacos (Bearer) utilizados para autenticación API.
 * El token se almacena como un hash SHA-256 del token en texto plano.
 * Esto mantiene paridad total con Laravel Passport.
 */
@Entity('oauth_access_tokens')
export class OAuthAccessToken {
  @PrimaryColumn({ type: 'varchar', length: 100 })
  id: string;

  @Column({ name: 'user_id', nullable: true })
  userId: number;

  @Column({ name: 'client_id', nullable: true })
  clientId: number;

  @Column({ length: 255, nullable: true })
  name: string;

  @Column({ type: 'text', nullable: true })
  scopes: string;

  @Column({ type: 'boolean', default: false })
  revoked: boolean;

  @CreateDateColumn({ name: 'created_at', nullable: true })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at', nullable: true })
  updatedAt: Date;

  @Column({ name: 'expires_at', type: 'timestamp', nullable: true })
  expiresAt: Date;

  @ManyToOne(() => User, { nullable: true, eager: false })
  @JoinColumn({ name: 'user_id' })
  user: User;
}

