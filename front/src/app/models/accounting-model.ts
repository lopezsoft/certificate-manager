

export interface PaymentMethodsAccounts {
  id: number;
  account_id: number;
  payment_id: number;
  nature_of_account_id: number;
  account_name: string;
  account_nature_name: string;
  payment_method: string;
  state?: number;
}

export interface ClassOfAccounting {
  id: number;
  name: string;
  number: number;
  state?: number;
}

export interface AccountingGroups {
  id: number;
  class_account_id: number;
  accounting_group_name: string;
  number: number;
  state?: number;
}


export interface AccountingDocuments {
  id: number;
  category_id: number;
  code: string;
  voucher_name: string;
  cufe_algorithm: string;
  prefix: string;
  electronic: boolean;
  apply_notes: boolean;
  invoice: boolean;
  active: boolean;
  post: boolean;
}

export interface Accounts {
  id: number;
  account_type_id?: number;
  account_id?: number;
  currency_id: number;
  account_name: string;
  description: string;
  account_number: string;
  state?: number;
}


export interface CorrectionNotes {
  id: number;
  document_id: number;
  code: string;
  description: string;
}

export interface NaturesOfAccount {
  id: number;
  account_nature_name: string;
  state?: number;
}
