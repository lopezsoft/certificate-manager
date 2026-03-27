import {
  Column,
  Entity,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { GeneralSettingCompany } from './general-setting-company.entity';

@Entity('general_settings')
export class GeneralSetting {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 120 })
  key: string;

  @Column({ type: 'text', nullable: true })
  value: string;

  @Column({ nullable: true })
  label: string;

  @Column({ name: 'input_type', nullable: true })
  inputType: string;

  @Column({ name: 'is_active', type: 'boolean', default: true })
  isActive: boolean;

  @OneToMany(() => GeneralSettingCompany, (gsc) => gsc.generalSetting)
  companySettings: GeneralSettingCompany[];
}
