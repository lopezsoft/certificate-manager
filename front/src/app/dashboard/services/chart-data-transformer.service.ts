import { Injectable } from '@angular/core';
import { ConsumeByYear, ConsumeByYearAndMonth } from '../../models/dashboard-model';

export interface MonthlyTrendData {
  month: string;
  monthNumber: number;
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
    // Inicializar todos los meses del año con valores en 0
    const allMonths = [
      { num: 1, name: 'Enero' },
      { num: 2, name: 'Febrero' },
      { num: 3, name: 'Marzo' },
      { num: 4, name: 'Abril' },
      { num: 5, name: 'Mayo' },
      { num: 6, name: 'Junio' },
      { num: 7, name: 'Julio' },
      { num: 8, name: 'Agosto' },
      { num: 9, name: 'Septiembre' },
      { num: 10, name: 'Octubre' },
      { num: 11, name: 'Noviembre' },
      { num: 12, name: 'Diciembre' }
    ];

    const monthMap = new Map<number, { month: string; processed: number; processing: number }>();

    // Inicializar todos los meses con 0
    allMonths.forEach(m => {
      monthMap.set(m.num, { month: m.name, processed: 0, processing: 0 });
    });

    // Llenar con datos reales
    data.forEach(item => {
      const monthNumber = item.nmonth;
      
      if (monthMap.has(monthNumber)) {
        const stats = monthMap.get(monthNumber)!;

        if (item.request_status === 'PROCESSED') {
          stats.processed += item.total || 0;
        } else if (item.request_status === 'PROCESSING') {
          stats.processing += item.total || 0;
        }
      }
    });

    // Retornar todos los meses ordenados (1-12)
    return Array.from(monthMap.entries())
      .sort((a, b) => a[0] - b[0])
      .map(([monthNumber, stats]) => ({
        month: stats.month,
        monthNumber,
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
