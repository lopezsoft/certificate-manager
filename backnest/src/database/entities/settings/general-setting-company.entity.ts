import {
  Column,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { Company } from '../company.entity';
import { GeneralSetting } from './general-setting.entity';

@Entity('general_setting_companies')
export class GeneralSettingCompany {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'company_id' })
  companyId: number;

  @Column({ name: 'general_setting_id' })
  generalSettingId: number;

  @Column({ type: 'text', nullable: true })
  value: string;

  @Column({ name: 'is_active', type: 'boolean', default: true })
  isActive: boolean;

  @ManyToOne(() => Company, (c) => c.settingCompanies, { nullable: true })
  @JoinColumn({ name: 'company_id' })
  company: Company;

  @ManyToOne(() => GeneralSetting, (gs) => gs.companySettings, {
    nullable: true,
    eager: true,
  })
  @JoinColumn({ name: 'general_setting_id' })
  generalSetting: GeneralSetting;
}
