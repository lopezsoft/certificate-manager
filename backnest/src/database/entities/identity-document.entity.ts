import { Column, Entity, OneToMany, PrimaryGeneratedColumn } from 'typeorm';

@Entity('identity_documents')
export class IdentityDocument {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 20, unique: true })
  code: string;

  @Column({ name: 'document_name', length: 120 })
  documentName: string;
}
