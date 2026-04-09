import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  OneToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from 'typeorm';
import { Company } from './company.entity';
import { City } from './location/city.entity';
import { IdentityDocument } from './identity-document.entity';
import { TypeOrganization } from './type-organization.entity';
import { FileManager } from './file-manager.entity';
import { ChangeHistory } from './change-history.entity';
import { DocumentAnalysisResult } from './document-analysis-result.entity';

@Entity('certificate_requests')
export class CertificateRequest {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'varchar', length: 36, unique: true, nullable: true })
  uuid: string;

  @Column({ name: 'company_id' })
  companyId: number;

  @Column({ name: 'city_id', nullable: true })
  cityId: number;

  @Column({ name: 'identity_document_id', nullable: true })
  identityDocumentId: number;

  @Column({ name: 'type_organization_id', nullable: true })
  typeOrganizationId: number;

  @Column({ name: 'company_name', length: 120, nullable: true })
  companyName: string;

  @Column({ length: 30, nullable: true })
  dni: string;

  @Column({ nullable: true })
  dv: number;

  @Column({ length: 255, nullable: true })
  address: string;

  @Column({ name: 'document_number', length: 30, nullable: true })
  documentNumber: string;

  @Column({ length: 30, nullable: true })
  phone: string;

  @Column({ length: 30, nullable: true })
  mobile: string;

  @Column({ name: 'legal_representative', length: 120, nullable: true })
  legalRepresentative: string;

  @Column({ type: 'text', nullable: true })
  info: string;

  @Column({ name: 'request_status', length: 20, default: 'DRAFT' })
  requestStatus: string;

  @Column({ name: 'postal_code', length: 20, nullable: true })
  postalCode: string;

  @Column({ type: 'int', nullable: true })
  life: number;

  @Column({ name: 'base_path', nullable: true })
  basePath: string;

  @Column({ name: 'document_type', length: 50, nullable: true })
  documentType: string;

  @Column({ length: 255, nullable: true })
  pin: string;

  @Column({ name: 'expiration_date', type: 'timestamp', nullable: true })
  expirationDate: Date;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;

  // Relaciones eager (equivale al $with de Laravel)
  @ManyToOne(() => IdentityDocument, { nullable: true, eager: true })
  @JoinColumn({ name: 'identity_document_id' })
  identity: IdentityDocument;

  @ManyToOne(() => TypeOrganization, { nullable: true, eager: true })
  @JoinColumn({ name: 'type_organization_id' })
  organization: TypeOrganization;

  @ManyToOne(() => City, { nullable: true, eager: true })
  @JoinColumn({ name: 'city_id' })
  city: City;

  @ManyToOne(() => Company, (c) => c.certificateRequests, { nullable: true })
  @JoinColumn({ name: 'company_id' })
  company: Company;

  @OneToMany(() => FileManager, (f) => f.certificateRequest, { eager: true })
  files: FileManager[];

  @OneToMany(() => ChangeHistory, (h) => h.certificateRequest, { eager: true })
  history: ChangeHistory[];

  @OneToMany(() => DocumentAnalysisResult, (a) => a.certificateRequest)
  analysisResults: DocumentAnalysisResult[];

  @OneToOne(() => DocumentAnalysisResult, (a) => a.certificateRequest, {
    nullable: true,
    eager: false,
  })
  latestAnalysis: DocumentAnalysisResult;
}
