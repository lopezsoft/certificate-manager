import { Injectable } from '@angular/core';
import { ConsumeByYear, ConsumeByYearAndMonth } from '../../models/dashboard-model';

export interface DashboardKPIs {
  totalCertificates: number;
  totalCompanies: number;
  processingRequests: number;
  averagePerCompany: number;
  growthRate: string;
}

export interface MonthlyStats {
  total: number;
  processed: number;
  processing: number;
  percentageProcessed: number;
}

@Injectable({
  providedIn: 'root'
})
export class DashboardMetricsService {

  constructor() { }

  public calculateTotalCertificates(data: ConsumeByYear[] | ConsumeByYearAndMonth[]): number {
    if (!data || data.length === 0) return 0;
    
    let sum = 0;
    for (const item of data) {
      const total = typeof item.total === 'string' ? parseInt(item.total, 10) : item.total;
      sum += isNaN(total) ? 0 : total;
    }
    return sum;
  }

  public countProcessingRequests(data: ConsumeByYear[] | ConsumeByYearAndMonth[]): number {
    if (!data || data.length === 0) return 0;
    return data.filter(item => item.request_status === 'PROCESSING').length;
  }

  public calculatePercentageVariation(current: number, previous: number): number {
    if (previous === 0) return current > 0 ? 100 : 0;
    return ((current - previous) / previous) * 100;
  }

  public determineGrowthRate(variation: number): string {
    if (variation > 10) return 'Alto';
    if (variation > 0) return 'Moderado';
    if (variation === 0) return 'Estable';
    return 'Decrecimiento';
  }

  public calculateAverage(total: number, count: number): number {
    return count > 0 ? Math.round(total / count) : 0;
  }

  public calculateKPIs(data: ConsumeByYear[], previousData?: ConsumeByYear[]): DashboardKPIs {
    const totalCertificates = this.calculateTotalCertificates(data);
    const totalCompanies = new Set(data.map(item => item.company_id)).size;
    const processingRequests = this.countProcessingRequests(data);
    const averagePerCompany = this.calculateAverage(totalCertificates, totalCompanies);

    let growthRate = 'N/A';
    if (previousData && previousData.length > 0) {
      const previousTotal = this.calculateTotalCertificates(previousData);
      const variation = this.calculatePercentageVariation(totalCertificates, previousTotal);
      growthRate = this.determineGrowthRate(variation);
    }

    return {
      totalCertificates,
      totalCompanies,
      processingRequests,
      averagePerCompany,
      growthRate
    };
  }

  public getMonthlyStats(data: ConsumeByYearAndMonth[]): MonthlyStats {
    const total = this.calculateTotalCertificates(data);
    const processed = data
      .filter(item => item.request_status === 'PROCESSED')
      .reduce((sum, item) => {
        const val = typeof item.total === 'string' ? parseInt(item.total, 10) : item.total;
        return sum + (isNaN(val) ? 0 : val);
      }, 0);
    const processing = data
      .filter(item => item.request_status === 'PROCESSING')
      .reduce((sum, item) => {
        const val = typeof item.total === 'string' ? parseInt(item.total, 10) : item.total;
        return sum + (isNaN(val) ? 0 : val);
      }, 0);

    return {
      total,
      processed,
      processing,
      percentageProcessed: total > 0 ? (processed / total) * 100 : 0
    };
  }
}
