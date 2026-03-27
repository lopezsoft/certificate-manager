import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
  BeforeInsert,
} from 'typeorm';
import { v4 as uuidv4 } from 'uuid';
import { CertificateRequest } from './certificate-request.entity';

@Entity('file_managers')
export class FileManager {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'uuid', unique: true, nullable: true })
  uuid: string;

  @Column({ name: 'certificate_request_id' })
  certificateRequestId: number;

  @Column({ name: 'file_path', nullable: true })
  filePath: string;

  @Column({ name: 'file_name', nullable: true })
  fileName: string;

  @Column({ name: 'file_type', nullable: true })
  fileType: string;

  @Column({ name: 'file_size', nullable: true, type: 'bigint' })
  fileSize: number;

  @Column({ name: 'original_name', nullable: true })
  originalName: string;

  @Column({ length: 50, nullable: true })
  extension: string;

  @Column({ nullable: true })
  description: string;

  @Column({ nullable: true })
  category: string;

  @Column({ name: 'is_active', type: 'boolean', default: true })
  isActive: boolean;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;

  @BeforeInsert()
  generateUuid() {
    if (!this.uuid) {
      this.uuid = uuidv4();
    }
  }

  @ManyToOne(() => CertificateRequest, (cr) => cr.files, { nullable: true })
  @JoinColumn({ name: 'certificate_request_id' })
  certificateRequest: CertificateRequest;
}
