import { Column, Entity, PrimaryGeneratedColumn } from 'typeorm';

@Entity('languages')
export class Language {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 10 })
  code: string;

  @Column({ length: 80 })
  name: string;

  @Column({ name: 'native_name', length: 80, nullable: true })
  nativeName: string;

  @Column({ name: 'is_active', type: 'boolean', default: true })
  isActive: boolean;
}
