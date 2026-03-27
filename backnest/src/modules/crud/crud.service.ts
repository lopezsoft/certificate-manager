import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DataSource, Repository } from 'typeorm';
import { GeneralSetting } from '@database/entities/settings/general-setting.entity';
import { GeneralSettingCompany } from '@database/entities/settings/general-setting-company.entity';
import { ReportHeader } from '@database/entities/settings/report-header.entity';

@Injectable()
export class CrudService {
  constructor(
    @InjectRepository(GeneralSetting)
    private readonly settingRepo: Repository<GeneralSetting>,
    @InjectRepository(GeneralSettingCompany)
    private readonly settingCompanyRepo: Repository<GeneralSettingCompany>,
    @InjectRepository(ReportHeader)
    private readonly reportHeaderRepo: Repository<ReportHeader>,
    private readonly dataSource: DataSource,
  ) { }

  private readonly tableMap: Record<string, string> = {
    T001: 'companies',
    T002: 'product_price_list',
    T003: 'sellers',
    T004: 'cost_centers',
    T005: 'document_reception_people',
    T006: 'accounting_groups',
    T007: 'automated_accounting_accounts',
    T008: 'automated_accounting_account_taxes',
    T009: 'payment_methods',
    T010: 'means_payment',
    T011: 'accounting_transaction_type',
    T012: 'taxes',
    T013: 'banks',
    T014: 'bank_account_type',
    T015: 'shipping_frequency',
    T016: 'destination_environme',
    T017: 'additional_document_reference',
    T018: 'accounting_documents',
    T019: 'correction_accounting_notes',
    T020: 'user_types',
    T021: 'identity_documents',
    T022: 'type_organization',
    T023: 'operation_types',
    T024: 'time_limit',
    T025: 'tax_level',
    T026: 'tax_regime',
    T027: 'branch_offices',
    T028: 'company_departments',
    T029: 'wineries_departments',
    T030: 'type_persons',
    T031: 'ep_contract_type',
    T032: 'ep_worker_type',
    T033: 'ep_worker_subtype',
    T034: 'ep_payroll_period',
    T035: 'standard_measurement_units',
    T036: 'trademarks',
    T037: 'categories',
    T038: 'product_class',
    T039: 'type_item_identifications',
    T040: 'natures_of_account',
    T041: 'discount_codes',
    T042: 'general_setting_companies',
    T043: 'software_information',
    T044: 'cash_registers',
    T045: 'cash_register_sessions',
    T046: 'cash_register_user_access',
    T047: 'points_of_sale',
    T048: 'point_of_sale_resolutions',
    T049: 'products',
    T050: 'customer_accounting_accounts',
    T051: 'products_accounting_account',
    T052: 'tax_accounting_account',
    T053: 'business_customers',
    T054: 'customers',
    T055: 'category_accounting_account',
    T056: 'person_references',
    T057: 'product_providers',
    T058: 'child_products',
    T059: 'product_other_taxes',
    T060: 'currency',
    T061: 'person_branch_offices',
    T062: 'mandates_items',
  };

  async getSettings(): Promise<GeneralSetting[]> {
    return this.settingRepo.find({ where: { isActive: true } });
  }

  async getCompanySettings(companyId: number): Promise<GeneralSettingCompany[]> {
    return this.settingCompanyRepo.find({ where: { companyId } });
  }

  async getReportHeader(companyId: number): Promise<ReportHeader | null> {
    return this.reportHeaderRepo.findOne({ where: { companyId } });
  }

  async upsertReportHeader(
    companyId: number,
    data: Partial<ReportHeader>,
  ): Promise<ReportHeader> {
    let header = await this.reportHeaderRepo.findOne({ where: { companyId } });

    if (header) {
      Object.assign(header, data);
    } else {
      header = this.reportHeaderRepo.create({ companyId, ...data });
    }

    return this.reportHeaderRepo.save(header);
  }

  async crudRead(
    params: Record<string, any>,
    id: number | null,
    companyId?: number,
  ) {
    const table = this.resolveTable(params.tbPrefix);
    const limit = Number(params.limit ?? 15);
    const page = Number(params.page ?? 1);
    const query = params.query ? String(params.query) : '';
    const where = this.parseJson(params.where) ?? {};
    const order = this.parseJson(params.order) ?? {};

    const columns = await this.getColumns(table);
    const pk = await this.getPrimaryKey(table);
    const hasCompanyId = columns.includes('company_id');

    let qb = this.dataSource
      .createQueryBuilder()
      .from(table, 't')
      .select('t.*');

    if (id !== null) {
      qb = qb.andWhere(`t.${pk} = :id`, { id });
    }

    if (hasCompanyId && companyId) {
      qb = qb.andWhere('t.company_id = :companyId', { companyId });
    }

    Object.entries(where).forEach(([key, value]) => {
      if (columns.includes(key)) {
        qb = qb.andWhere(`t.${key} = :w_${key}`, { [`w_${key}`]: value });
      }
    });

    if (query.length > 0) {
      const searchCols = columns.filter((c) => c !== pk);
      if (searchCols.length > 0) {
        const conditions = searchCols.map((c) => `CAST(t.${c} AS TEXT) ILIKE :q`);
        qb = qb.andWhere(`(${conditions.join(' OR ')})`, { q: `%${query}%` });
      }
    }

    Object.entries(order).forEach(([key, dir]) => {
      if (columns.includes(key)) {
        const normalized = String(dir).toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
        qb = qb.addOrderBy(`t.${key}`, normalized as 'ASC' | 'DESC');
      }
    });

    const totalItems = await qb.getCount();
    const items = await qb
      .offset((page - 1) * limit)
      .limit(limit)
      .getRawMany();

    return {
      __paginated: true as const,
      items,
      meta: {
        currentPage: page,
        totalPages: Math.max(1, Math.ceil(totalItems / limit)),
        itemsPerPage: limit,
        totalItems,
      },
    };
  }

  async crudCreate(body: Record<string, any>, companyId?: number) {
    const table = this.resolveTable(body.tbPrefix);
    const columns = await this.getColumns(table);
    const pk = await this.getPrimaryKey(table);
    const hasCompanyId = columns.includes('company_id');
    const records = this.normalizeRecords(body.records);

    const payload = records.map((r) => {
      const clean: Record<string, any> = {};
      Object.entries(r).forEach(([k, v]) => {
        if (k !== pk && columns.includes(k)) clean[k] = v;
      });
      if (hasCompanyId && companyId && clean.company_id === undefined) {
        clean.company_id = companyId;
      }
      return clean;
    });

    if (payload.length === 0) return [];

    const result = await this.dataSource
      .createQueryBuilder()
      .insert()
      .into(table)
      .values(payload)
      .returning('*')
      .execute();

    return result.raw;
  }

  async crudUpdate(id: number, body: Record<string, any>, companyId?: number) {
    const table = this.resolveTable(body.tbPrefix);
    const columns = await this.getColumns(table);
    const pk = await this.getPrimaryKey(table);
    const hasCompanyId = columns.includes('company_id');
    const records = this.normalizeRecords(body.records);

    for (const record of records) {
      const data: Record<string, any> = {};
      Object.entries(record).forEach(([k, v]) => {
        if (k !== pk && columns.includes(k)) data[k] = v;
      });
      const keyValue = record[pk] ?? id;
      let qb = this.dataSource
        .createQueryBuilder()
        .update(table)
        .set(data)
        .where(`${pk} = :id`, { id: keyValue });

      if (hasCompanyId && companyId) {
        qb = qb.andWhere('company_id = :companyId', { companyId });
      }

      await qb.execute();
    }
  }

  async crudDelete(id: number, params: Record<string, any>, companyId?: number) {
    const table = this.resolveTable(params.tbPrefix);
    const columns = await this.getColumns(table);
    const pk = await this.getPrimaryKey(table);
    const hasCompanyId = columns.includes('company_id');

    let selectQb = this.dataSource
      .createQueryBuilder()
      .from(table, 't')
      .select('t.*')
      .where(`t.${pk} = :id`, { id });

    if (hasCompanyId && companyId) {
      selectQb = selectQb.andWhere('t.company_id = :companyId', { companyId });
    }

    const rows = await selectQb.getRawMany();

    let deleteQb = this.dataSource
      .createQueryBuilder()
      .delete()
      .from(table)
      .where(`${pk} = :id`, { id });

    if (hasCompanyId && companyId) {
      deleteQb = deleteQb.andWhere('company_id = :companyId', { companyId });
    }

    await deleteQb.execute();
    return rows;
  }

  private resolveTable(tbPrefix?: string): string {
    if (!tbPrefix) {
      throw new Error('No se ha especificado el prefijo de la tabla');
    }
    const table = this.tableMap[tbPrefix];
    if (!table) {
      throw new Error('Prefijo de tabla no soportado');
    }
    return table;
  }

  private parseJson(value: any): Record<string, any> | null {
    if (!value) return null;
    if (typeof value === 'object') return value;
    try {
      return JSON.parse(String(value));
    } catch {
      return null;
    }
  }

  private normalizeRecords(records: any): Array<Record<string, any>> {
    if (!records) return [];
    const parsed = typeof records === 'string' ? this.parseJson(records) : records;
    if (!parsed) return [];
    return Array.isArray(parsed) ? parsed : [parsed];
  }

  private async getColumns(table: string): Promise<string[]> {
    const rows: Array<{ column_name: string }> = await this.dataSource.query(
      `SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=$1`,
      [table],
    );
    return rows.map((r) => r.column_name);
  }

  private async getPrimaryKey(table: string): Promise<string> {
    const rows: Array<{ column_name: string }> = await this.dataSource.query(
      `SELECT kcu.column_name
       FROM information_schema.table_constraints tc
       JOIN information_schema.key_column_usage kcu
         ON tc.constraint_name = kcu.constraint_name
        AND tc.table_schema = kcu.table_schema
      WHERE tc.constraint_type = 'PRIMARY KEY'
        AND tc.table_schema = 'public'
        AND tc.table_name = $1
      LIMIT 1`,
      [table],
    );
    return rows[0]?.column_name ?? 'id';
  }
}
