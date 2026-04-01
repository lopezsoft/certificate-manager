import { Column, Entity, PrimaryGeneratedColumn } from 'typeorm';

@Entity('type_organization')
export class TypeOrganization {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 120 })
  name: string;

  @Column({ type: 'text', nullable: true })
  description: string;
}
