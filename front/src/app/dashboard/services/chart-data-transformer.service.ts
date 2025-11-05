import { Injectable } from '@angular/core';
import { ConsumeByYear, ConsumeByYearAndMonth } from '../../models/dashboard-model';

export interface MonthlyTrendData {
  month: string;
  processed: number;
  processing: number;
}

export interface CompanyTotal {
  company: string;
  total: number;
}

@Injectable({
  providedIn: 'root'
})
export class ChartDataTransformerService {

  constructor() { }

  groupByMonth(data: ConsumeByYearAndMonth[]): MonthlyTrendData[] {
    const monthMap = new Map<string, { processed: number; processing: number }>();

    data.forEach(item => {
      const month = item.monthname;
      if (!monthMap.has(month)) {
        monthMap.set(month, { processed: 0, processing: 0 });
      }

      const stats = monthMap.get(month)!;

      if (item.request_status === 'PROCESSED') {
        stats.processed += item.total || 0;
      } else if (item.request_status === 'PROCESSING') {
        stats.processing += item.total || 0;
      }
    });

    return Array.from(monthMap.entries()).map(([month, stats]) => ({
      month,
      processed: stats.processed,
      processing: stats.processing
    }));
  }

  getTopCompanies(data: ConsumeByYear[], limit: number = 10): CompanyTotal[] {
    return data
      .map(item => ({
        company: item.company_name,
        total: item.total || 0
      }))
      .sort((a, b) => b.total - a.total)
      .slice(0, limit);
  }

  getMonthlyTotal(data: ConsumeByYearAndMonth[]): number {
    let sum = 0;
    for (const item of data) {
      sum += item.total || 0;
    }
    return sum;
  }
}
