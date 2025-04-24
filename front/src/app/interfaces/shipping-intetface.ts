import {Currency} from "../models/general-model";

export interface UserType {
  id: number;
  user_type_name: string;
  type: number;
  active: number;
}

export interface Company {
  id: number;
  country_id: number | string;
  city_id: number | string;
  identity_document_id: number | string;
  type_organization_id: number | string;
  tax_regime_id: number | string;
  tax_level_id: number | string;
  company_name: string;
  trade_name?: string;
  dni: string;
  dv: number;
  address: string;
  merchant_registration: string;
  postal_code: string;
  mobile?: string;
  phone?: string;
  image: string;
  mime: string;
  email: string;
  verified_email: number;
  web: string;
  active: number;
  full_path_image: string;
}

export interface User {
  id: number;
  type_id: number;
  first_name: string;
  last_name: string;
  email: string;
  avatar: string;
  active: number;
  name: string;
  avatarUrl: string;
  company: Company;
  user_type: UserType;
}

export interface PaymentMethod {
  id: number;
  code: string;
  payment_method: string;
  active: number;
  payment_method_code?: string;
  payment_due_date?: string;
  duration_measure?: string;
  duration_measure_unit_code: string;
  payment_id: number;
}

export interface MeansPayment {
  id: number;
  payment_method: string;
  code: string;
  active: number;
}

export interface Payment {
  id: number;
  paymentMethod: PaymentMethod;
  meansPayment: MeansPayment;
  payment_due_date: string;
  currency_id: number;
  value_paid: number;
}

export interface TaxTotal {
  tax_id: number;
  tax_amount: number;
  taxable_amount: number;
  percent: number;
  is_fixed_value: boolean;
}

export interface LegalMonetaryTotals {
  line_extension_amount: string;
  tax_exclusive_amount: string;
  tax_inclusive_amount: string;
  charge_total_amount: number;
  allowance_total_amount: number;
  pre_paid_amount: number;
  payable_amount: number;
}

export interface QuantityUnits {
  id: number;
  name: string;
  code: string;
}

export interface TypeItemIdentifications {
  id: number;
  name: string;
  code: string;
  code_agency: string;
}

export interface Line {
  invoiced_quantity: string;
  quantity_units_id: string;
  line_extension_amount: string;
  free_of_charge_indicator: string;
  description: string;
  code: string;
  type_item_identifications_id: string;
  reference_price_id: string;
  price_amount: string;
  base_quantity: string;
  tax_totals: TaxTotal[];
  mu: string;
  total: number;
  tax_retentions: any[];
  quantity_units: QuantityUnits;
  type_item_identifications: TypeItemIdentifications;
}

export interface TypeDocument {
  id: number;
  code: string;
  voucher_name: string;
  cufe_algorithm: string;
  prefix: string;
  electronic: number;
  apply_notes: number;
  invoice: number;
  active: number;
}


export interface Customer {
  email: string;
  company: Company;
  dni?: string;
  city: {
    id: number;
    city_code: string;
    name_city: string;
    department: {
      id: number;
      code: string;
      name_department: string;
      abbreviation: string;
    };
  };
  country: {
    id: number;
    ContinentA2: string;
    abbreviation_A2: string;
    abbreviation_A3: string;
    FIPS: string;
    country_name: string;
    language: string;
    phone_code: string;
    TLD: string;
  };
  identityDocument: {
    id: number;
    code: string;
    document_name: string;
    abbreviation: string;
    active: number;
  };
  typeOrganization: {
    id: number;
    code: number;
    description: string;
  };
  taxLevel: {
    id: number;
    code: string;
    description: string;
  };
  taxRegime: {
    id: number;
    code: string;
    description: string;
    active: number;
  };
  postal_code: string;
  points: number;
  name: string;
  avatarUrl?: string;
}

export interface Shipping {
  id: number;
  resolution_id: number;
  type_document_id: number;
  operation_type_id: number;
  document_number: string;
  XmlDocumentKey: string;
  XmlDocumentName: string;
  jsonPath: string;
  xmlPath: string;
  zipPath: string;
  qrPath: string;
  pdfPath: string;
  attachedPath: string;
  attachedZipPath: string;
  payable_amount: number;
  invoice_date: string;
  is_valid: number;
  status: number;
  order_number?: string | null;
  checked?: boolean;
  jsonData: {
    user: User;
    language: number;
    operationTypeId: number;
    typeDocumentId: number;
    documentNumber: string;
    invoiceDate: string;
    invoiceTime: string;
    expirationDate?: string | null;
    notes?: string | null;
    cufe: string;
    qrcode: string;
    qrDian: string;
    qrData: string;
    taxRetentions: any[];
    payment_value: number;
    customer: Customer;
    payment: Payment[];
    allowanceCharges: any[];
    orderReference?: string | null;
    paymentExchangeRate?: string | null;
    taxTotals: TaxTotal[];
    legalMonetaryTotals: LegalMonetaryTotals;
    totalPayment: string;
    totalDiscount: number;
    totalCharges: number;
    lines: Line[];
    typeDocument: TypeDocument;
    currency: Currency;
    additionalDocumentReferences?: string | null;
    discrepancyResponse?: string | null;
    billingReference?: string | null;
    pointsOfSale?: string | null;
    softwareManufacturer?: string | null;
    health?: string | null;
    prepaidPayments?: string | null;
  };
  createdAt: string;
  updatedAt: string;
  document: TypeDocument;
  user: User;
  operation_type: {
    id: number;
    code: string;
    name: string;
  };
  resolution: {
    id: number;
    type_document_id: number;
    headerline1: string;
    headerline2: string;
    footline1: string;
    footline2: string;
    footline3: string;
    footline4: string;
    image: string;
    mime: string;
    prefix: string;
    invoice_name: string;
    range_from: number;
    range_up: number;
    initial_number: number;
    date_from: string;
    date_up: string;
    resolution_number: string;
    technical_key: string;
    active: number;
    state: number;
    timestamp: string;
    number?: string | null;
    next_consecutive: string;
    type_document: TypeDocument;
  };
  json_data: {
    id: number;
    shipping_id: number;
    jdata: any;
    created_at: string;
    updated_at: string;
  };
}
