
export interface Brands {
  id: number;
  brand_name: string;
  state?: number;
}

export interface ClassOfProducts {
  id: number;
  product_class_name: string;
  active?: boolean;
}

export interface Attributes {
  id: number;
  attribute_name: string;
  state?: number;
}

export interface Terms {
  id: number;
  attribute_id: number;
  term_name: string;
  term_description: string;
  attribute_name?: string;
  term_color?: string;
  term_img?: string;
  state?: number;
}

export interface MeasurementUnits {
  id: number;
  base_unit_id: number;
  unit_description: string;
  unit_name?: string;
  abbre_unit?: string;
  factor: number;
  state?: number;
}

export interface ProductsMeasurementUnits {
  id: number;
  unit_id: number;
  base_unit_id: number;
  unit_description: string;
  unit_name?: string;
  standard_unit_name?: string;
  abbre_unit?: string;
  factor: number;
  purchase_cost: number;
  sale_price: number;
  state?: number;
}

export interface Items {
  unit_name?: string;
  unit_description?: string;
  abbre_unit?: string;
  tax_amount: number;
  factor: number;
  unit_id: number;
  base_factor: number;
  product_class_name: string;
  sku?: string;
  reason?: string;
  image?: string;
  qr_code: string;
  product_name: string;
  notes: string;
  description_sales: string;
  shopping_description: string;
  barcode: string;
  rate_name: string;
  internal_code: string;
  state: number;
  sale_price: number;
  quantity?: number;
  price?: number;
  discount?: number;
  vat?: number;
  other_tax?: number;
  total?: number;
  purchase_cost: number;
  tax_sales: number;
  tax_bill_rate: number;
  tax_sale_rate: number;
  tax_bill: number;
  stock_min: number;
  stock_max: number;
  tax_sales_id: number;
  stock: number;
  initial_stock: number;
  brand_id?: number;
  category_id?: number;
  sub_category_id?: number;
  percentage_gain: number;
  tax_bill_id: number;
  type_item_identifications_id: number;
  item_type_id: number;
  class_id: number;
  average_cost: number;
  recipe: boolean;
  edit_name? : boolean;
  perishable: boolean;
  day_without_vat: boolean;
  vat_included: boolean;
  selling_out_of_inventory: boolean;
  id: number;
}

export interface Categories {
  id: number;
  parent_id?: number;
  category_name: string;
  imagen?: string;
  parent_name?: string;
  state?: number;
}


export interface ItemsType {
  id: number;
  product_class_id: number;
  name_type: string;
  state?: number;
}

export interface ItemsTypeAccounts {
  id: number;
  item_type_id?: number;
  product_class_id?: number;
  nature_of_account_id: number;
  account_type_id: number;
  account_id: number;
  name_type: string;
  account_number?: string;
  account_name: string;
  group_name?: string;
  product_class_name?: string;
  account_nature_name?: string;
}

export interface AccountTypes {
  id: number;
  name_type: string;
}

export interface TypeItemIdentifications {
  id: number;
  name: string;
  code: string;
  code_agency: string;
}

export interface OtherTaxes {
  id: number;
  product_id?: number;
  tax_rate_id: number;
  rate_name: string;
  rate_abbre: string;
  rate_value: number;
  decimal_rate: number;
  additional_tax: number;
  tax_value?: number;
  base?: number;
  state?: number;
}
