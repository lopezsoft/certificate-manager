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

export enum PersonType {
  NATURAL = 'natural',
  JURIDICA = 'juridica',
}

@Entity('document_analysis_results')
export class DocumentAnalysisResult {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'certificate_request_id' })
  certificateRequestId: number;

  @Column({ name: 'file_manager_id', nullable: true })
  fileManagerId: number;

  @Column({ name: 'document_type', nullable: true })
  documentType: string;

  @Column({
    name: 'person_type',
    type: 'enum',
    enum: PersonType,
    nullable: true,
  })
  personType: PersonType;

  @Column({ name: 'ocr_provider', nullable: true })
  ocrProvider: string;

  @Column({ name: 'analysis_results', type: 'json', nullable: true })
  analysisResults: Record<string, any>;

  @Column({ name: 'validation_summary', type: 'json', nullable: true })
  validationSummary: Record<string, any>;

  @Column({ name: 'validation_errors', type: 'json', nullable: true })
  validationErrors: Record<string, any>;

  @Column({ name: 'extracted_data', type: 'json', nullable: true })
  extractedData: Record<string, any>;

  @Column({ name: 'is_valid', type: 'boolean', default: false })
  isValid: boolean;

  @Column({ name: 'is_processed', type: 'boolean', default: false })
  isProcessed: boolean;

  @Column({ name: 'has_errors', type: 'boolean', default: false })
  hasErrors: boolean;

  @Column({ name: 'identity_verified', type: 'boolean', default: false })
  identityVerified: boolean;

  @Column({ name: 'address_verified', type: 'boolean', default: false })
  addressVerified: boolean;

  @Column({ name: 'document_verified', type: 'boolean', default: false })
  documentVerified: boolean;

  @Column({ name: 'rut_verified', type: 'boolean', default: false })
  rutVerified: boolean;

  @Column({ name: 'chamber_verified', type: 'boolean', default: false })
  chamberVerified: boolean;

  @Column({ name: 'confidence_score', type: 'decimal', nullable: true })
  confidenceScore: number;

  @Column({ name: 'raw_text', type: 'text', nullable: true })
  rawText: string;

  @Column({ name: 'error_message', type: 'text', nullable: true })
  errorMessage: string;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;

  @ManyToOne(() => CertificateRequest, (cr) => cr.analysisResults, {
    nullable: true,
  })
  @JoinColumn({ name: 'certificate_request_id' })
  certificateRequest: CertificateRequest;
}
