export class CompaniesModel {
}

export interface Company {
  active?: boolean;
  city_id: number;
  merchant_registration?: string;
  trade_name?: string;
  tax_level_id: number;
  tax_regime_id: number;
  address: string;
  company_name: string;
  country_id: number;
  dni: string;
  dv: string;
  email: string;
  id: number;
  identity_document_id: number;
  image: string;
  full_path_image?: string;
  location: string;
  mime: string;
  mobile: string;
  phone: string;
  postal_code: string;
  type_organization_id: number;
  web: string;
}

export interface TypeOrganization {
  id: number;
  code: number;
  description: string;
}

export interface wineries
{
  id: number;
  branch_id: number;
  department_id?: number;
  winery_name: string;
  department_name?: string;
  state: number;
}
export interface Departments{
  id: number;
  branch_office_id: number;
  department_name: string;
  state: number;
}

export interface BranchOffice{
  id: number;
  currency_id:number;
  branch_id?:number;
  country_id:number;
  branch_name:string;
  branch?:string;
  postal_code:string;
  address:string;
  address2:string;
  location:string;
  email:string;
  mobile:string;
  phone:string;
  web:string;
  is_point_of_sale: number,
  state:number;
}
