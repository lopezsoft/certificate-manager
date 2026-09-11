/**
 * Interfaces para las estadísticas transversales de administrador.
 *
 * Endpoints:
 *   GET /admin/stats/payments-by-month?year=&company_id=
 *   GET /admin/stats/unused-quotas?company_id=&include_expired=
 */

export interface PaymentAmounts {
  orders: number;
  certificates: number;
  subtotal: number;
  tax_amount: number;
  total_amount: number;
}

export interface PaymentMonthTotal extends PaymentAmounts {
  month: number;
  month_name: string;
}

export interface CompanyPayments extends PaymentAmounts {
  company_id: number;
  company_name: string;
  dni: string | null;
  email: string | null;
  has_agreement: boolean;
  /** Clave: número de mes (1-12). Solo meses con pagos. */
  months: { [month: number]: PaymentAmounts };
}

export interface PaymentsByMonth {
  year: number;
  available_years: number[];
  months: PaymentMonthTotal[];
  companies: CompanyPayments[];
  totals: PaymentAmounts & { companies: number };
}

export interface UnusedPostpaidQuota {
  quota_id: number;
  pricing_tier: string | null;
  allocated: number;
  used: number;
  remaining: number;
  period_start: string | null;
  period_end: string | null;
  status: string;
  is_expired: boolean;
  notes: string | null;
}

export interface UnusedPrepaidItems {
  order_uuid: string;
  purchased_at: string;
  vigencia: number;
  /** Certificados comprados en la orden */
  purchased: number;
  /** Certificados ya solicitados (items USED) */
  requested: number;
  /** Certificados sin solicitar (items PENDING) */
  pending: number;
}

export interface CompanyUnusedQuota {
  company_id: number;
  company_name: string;
  dni: string | null;
  email: string | null;
  has_agreement: boolean;
  postpaid: UnusedPostpaidQuota[];
  prepaid: UnusedPrepaidItems[];
  postpaid_remaining: number;
  /** Total comprado en órdenes pagadas */
  prepaid_purchased: number;
  /** Total ya solicitado */
  prepaid_requested: number;
  /** Total sin solicitar */
  prepaid_pending: number;
  prepaid_1_year: number;
  prepaid_2_year: number;
  total_unused: number;
}

export interface UnusedQuotas {
  include_expired: boolean;
  companies: CompanyUnusedQuota[];
  totals: {
    companies: number;
    postpaid_remaining: number;
    prepaid_purchased: number;
    prepaid_requested: number;
    prepaid_pending: number;
    total_unused: number;
  };
}
