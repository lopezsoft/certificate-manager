import {AccountingDocuments} from "./accounting-model";
import {Users} from "./users-model";

export interface StringMap {
  [key: string]: string;
}

export interface AdditionalDocumentReference {
  id: number;
  reference_document_id?: number;
  code: string;
  name_reference: string;
  reference_number?: string;
  reference_date?: string;
  state?: number;
}

export interface Banks {
  id: number;
  bank_name: string;
  state: number;
}

export interface Taxes {
  id: number;
  name_taxe: string;
  description: string;
  state: number;
  is_vat: boolean;
}

export interface CurrencySys {
  id: number;
  currency_id: number;
  exchange_rate_value: number;
  national_currency: number;
  currency_name: string;
  plural_name: string;
  singular_name: string;
  denomination: string;
  CurrencyISO: string;
  CurrencyName?: string;
  Money?: string;
  Symbol?: string;
  image?: string;
  state: number;
  currency: Currency;
}

export interface Currency {
  id: number;
  CurrencyISO: string;
  Language: string;
  CurrencyName: string;
  Money: string;
  Symbol: string;
  image: string;
  state: number;
  Format: string;
}

export interface CurrencyChange {
  success: boolean;
  value: number;
  amount: number;
}

export interface TaxRates {
  id: number;
  shipping_frequency_id: number;
  account_id?: number;
  taxe_id: number;
  rate_name: string;
  name_taxe?: string;
  rate_abbre: string;
  fecuency_name?: string;
  account_name?: string;
  rate_value: number;
  decimal_rate: number;
  additional_tax: number;
  state: number;
  is_exempt: boolean;
}

export interface ShippingFrequency {
  id: number;
  name: string;
  rate?: number;
  active?: boolean;
}

export interface TaxAccountingAccount {
  id: number;
  tax_rate_id: number;
  account_id: number;
  rate_value?: number;
  rate_name?: string;
  account_name?: string;
  rate_abbre?: number;
  state?: number;
}

export interface Certificate {
  id: number;
  company_id: number;
  name?: number;
  data?: string;
  description?: string;
  expiration_date?: string;
  password?: string;
  extension?: string;
}

export interface TestDocument {
  id: number;
  document_number: string;
  zipkey: string;
  document: AccountingDocuments
}

export interface TestProcess {
  id: number;
  user_id: number;
  software_id: number;
  uuid: string;
  status: string;
  status_description: string;
  error_message: StringMap;
  StatusDescription?: string;
  documents: TestDocument[];
}

export interface Software {
  id: number;
  company_id: number;
  environment_id: number;
  type_id: number;
  integration_type: number;
  testsetid?: string;
  initial_number: number;
  technical_key?: string;
  typeDescription?: string;
  account_id?: string;
  auth_token?: string;
  url?: string;
  pin?: string;
  test_process_status?: string;
  identification?: string;
  processStatusDescription?: string;
  environment?: DestinationEnvironment;
  test_process?: TestProcess;
  checked?: boolean;
}

export interface ProcessSoftware {
  id: number;
  uuid: string;
  state: string;
  error_message: null;
}

export interface SoftwareTest {
  id: number;
  process_id: number;
  user_id: number;
  software_id: number;
  invoice_number: string;
  zipkey: string;
  XmlDocumentKey: string;
  software: Software;
  user?: Users;
  process?: ProcessSoftware;
  document?: AccountingDocuments;
}

export interface DestinationEnvironment {
  id: number;
  code: number;
  environment_name?: string;
}


export interface Resolutions {
  id: number;
  active:number;
  company_id:number;
  category_id:number;
  date_from: string;
  date_up: string;
  footline1: string;
  footline2: string;
  footline3: string;
  footline4: string;
  headerline1: string;
  headerline2: string;
  image: string;
  initial_number: number;
  invoice_name: string;
  mime: string;
  prefix: string;
  range_from: number;
  range_up: number;
  resolution_number: string;
  state: number;
  type_document_id: number;
  voucher_name: string;
  code: string;
  electronic: boolean;
  apply_notes: boolean;
  invoice: boolean;
  pos: boolean;
  type_document: AccountingDocuments;
  signature_one?: string;
  signature_two?: string;
  technical_key: string;
}

export interface TypeOrganzation {
  id          : number;
  code        : number;
  description : string;
}

export interface PostalCode {
  id: number;
  city_id: number;
  city_code: string;
  postal_code: string;
  location: string;
}
export interface IdentityDocuments {
  id: number;
  code: string;
  document_name: string;
  abbrev: string;
  active: number;
  state: number,
}
export interface Department {
  id: number;
  code: string;
  name_department: string;
  abbreviation: string;
  country: Country;
}
export interface Cities {
  id: number;
  department_id: number;
  city_code?: string;
  name_department?: string;
  name_city: string;
  department: Department
}


export interface Country {
  id: number;
  abbreviation_A2: string;
  country_name: string;
  phone_code: string;
  image: string;
  active: boolean;
}

export interface TaxLevel {
  id          : number;
  code        : string;
  description : string;
}

export interface TaxRegime extends  TaxLevel{
  active      : number;
}


export interface MeansPayment {
  id: number;
  payment_method: string;
  code: string;
  active: boolean;
}

export interface PaymentMethods {
  id: number;
  payment_method: string;
  code: string;
  active: boolean;
}

export interface TimeLimits {
  id: number;
  term_name: string;
  term_value: number;
  months: number;
  active: boolean;
}
export interface OperationTypes {
  id: number;
  code: string;
  name: string;
}

export interface ReportsHeader {
  id: number;
  company_id: number;
  line1: string;
  line2: string;
  foot: string;
  image: string;
}

export interface ResponseDian {
  Envelope: Envelope;
}

export interface Envelope {
  Header: Header;
  Body: Body;
}

export interface Header {
  Action: Action;
  Security: Security;
}

export interface Action {
  _attributes: Attributes;
  _value: string;
}

export interface Attributes {
  mustUnderstand: string;
}

export interface Security {
  _attributes: Attributes;
  Timestamp: Timestamp;
}

export interface Timestamp {
  _attributes: IdAttribute;
  Created: string;
  Expires: string;
}

export interface IdAttribute {
  Id: string;
}

export interface Body {
  GetStatusZipResponse: GetStatusZipResponse;
}

export interface GetStatusZipResponse {
  GetStatusZipResult: GetStatusZipResult;
}

export interface GetStatusZipResult {
  DianResponse: DianResponse;
}

export interface DianResponse {
  ErrorMessage: any;
  IsValid: string;
  StatusCode: string;
  StatusDescription: string;
  StatusMessage: string;
  XmlBase64Bytes: string;
  XmlBytes: XmlBytes;
  XmlDocumentKey: string;
  XmlFileName: string;
}

export interface XmlBytes {
  _attributes: NilAttribute;
}

export interface NilAttribute {
  nil: string;
}

export interface ListValue {
  data_type: string;
  description: string;
  id: number;
  key_value: string;
  list_values: string;  // JSON string de un array
  max_value: number;
  min_value: number;
  tag: number;
  tooltip: string | null;
  value: string;
}


export interface SettingEntry {
  active: boolean;
  company_id: number;
  id: number;
  setting: ListValue;
  setting_id: number;
  value: string;
}



interface Document {
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

interface User {
  id: number;
  type_id: number;
  first_name: string;
  last_name: string;
  email: string;
  avatar: string;
  active: number;
  name: string;
  avatarUrl: string;
  user_type: UserType;
}

interface UserType {
  id: number;
  user_type_name: string;
  type: number;
  active: number;
}

interface OperationType {
  id: number;
  code: string;
  name: string;
}

interface Resolution {
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
  active: number;
  state: number;
  timestamp: string;
  number: string | null;
  next_consecutive: string;
  type_document: Document;
}
