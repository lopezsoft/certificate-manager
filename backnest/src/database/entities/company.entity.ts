import {
  Column,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { Country } from './location/country.entity';
import { City } from './location/city.entity';
import { IdentityDocument } from './identity-document.entity';
import { TypeOrganization } from './type-organization.entity';
import { GeneralSettingCompany } from './settings/general-setting-company.entity';
import { CertificateRequest } from './certificate-request.entity';

@Entity('companies')
export class Company {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'country_id', nullable: true })
  countryId: number;

  @Column({ name: 'city_id', nullable: true })
  cityId: number;

  @Column({ name: 'identity_document_id', nullable: true })
  identityDocumentId: number;

  @Column({ name: 'type_organization_id', nullable: true })
  typeOrganizationId: number;

  @Column({ name: 'company_name', length: 200 })
  companyName: string;

  @Column({ length: 30, nullable: true })
  dni: string;

  @Column({ nullable: true })
  dv: number;

  @Column({ length: 255, nullable: true })
  address: string;

  @Column({ name: 'city_name', length: 100, nullable: true })
  cityName: string;

  @Column({ length: 255, nullable: true })
  location: string;

  @Column({ name: 'postal_code', length: 20, nullable: true })
  postalCode: string;

  @Column({ length: 30, nullable: true })
  phone: string;

  @Column({ length: 150, nullable: true })
  email: string;

  @Column({ nullable: true })
  image: string;

  @ManyToOne(() => Country, { nullable: true, eager: true })
  @JoinColumn({ name: 'country_id' })
  country: Country;

  @ManyToOne(() => IdentityDocument, { nullable: true, eager: true })
  @JoinColumn({ name: 'identity_document_id' })
  identityDocument: IdentityDocument;

  @ManyToOne(() => TypeOrganization, { nullable: true, eager: true })
  @JoinColumn({ name: 'type_organization_id' })
  typeOrganization: TypeOrganization;

  @ManyToOne(() => City, { nullable: true, eager: true })
  @JoinColumn({ name: 'city_id' })
  city: City;

  @OneToMany(() => GeneralSettingCompany, (gsc) => gsc.company)
  settingCompanies: GeneralSettingCompany[];

  @OneToMany(() => CertificateRequest, (cr) => cr.company)
  certificateRequests: CertificateRequest[];
}
