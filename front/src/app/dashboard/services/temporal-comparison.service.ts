import { Injectable } from '@angular/core';
import { ConsumeByYear } from '../../models/dashboard-model';
import { DocumentStatusEnum } from '../../common/enums/DocumentStatus';

export interface YearComparison {
  currentYear: number;
  previousYear: number;
  currentTotal: number;
  previousTotal: number;
  difference: number;
  percentageChange: number;
  trend: 'up' | 'down' | 'stable';
}

export interface CompanyComparison {
  currentCount: number;
  previousCount: number;
  difference: number;
  percentageChange: number;
  trend: 'up' | 'down' | 'stable';
}

export interface ProcessingComparison {
  currentProcessing: number;
  previousProcessing: number;
  difference: number;
  percentageChange: number;
  trend: 'up' | 'down' | 'stable';
}

@Injectable({
  providedIn: 'root'
})
export class TemporalComparisonService {

  constructor() { }

  compareYears(
    currentYearData: ConsumeByYear[], 
    previousYearData: ConsumeByYear[],
    currentYear: number
  ): YearComparison {
    const currentTotal = currentYearData.reduce((sum, item) => 
      sum + parseInt(item.total || '0', 10), 0
    );

    const previousTotal = previousYearData.reduce((sum, item) => 
      sum + parseInt(item.total || '0', 10), 0
    );

    const difference = currentTotal - previousTotal;
    const percentageChange = previousTotal > 0 
      ? ((difference / previousTotal) * 100) 
      : 0;

    return {
      currentYear,
      previousYear: currentYear - 1,
      currentTotal,
      previousTotal,
      difference,
      percentageChange: Math.round(percentageChange * 100) / 100,
      trend: this.determineTrend(difference)
    };
  }

  compareActiveCompanies(
    currentYearData: ConsumeByYear[], 
    previousYearData: ConsumeByYear[]
  ): CompanyComparison {
    const currentCompanies = new Set(currentYearData.map(item => item.company_id));
    const previousCompanies = new Set(previousYearData.map(item => item.company_id));

    const currentCount = currentCompanies.size;
    const previousCount = previousCompanies.size;
    const difference = currentCount - previousCount;
    const percentageChange = previousCount > 0 
      ? ((difference / previousCount) * 100) 
      : 0;

    return {
      currentCount,
      previousCount,
      difference,
      percentageChange: Math.round(percentageChange * 100) / 100,
      trend: this.determineTrend(difference)
    };
  }

  compareProcessingRequests(
    currentYearData: ConsumeByYear[], 
    previousYearData: ConsumeByYear[]
  ): ProcessingComparison {
    const currentProcessing = currentYearData.filter(item => 
      item.request_status === DocumentStatusEnum.PROCESSING
    ).length;

    const previousProcessing = previousYearData.filter(item => 
      item.request_status === DocumentStatusEnum.PROCESSING
    ).length;

    const difference = currentProcessing - previousProcessing;
    const percentageChange = previousProcessing > 0 
      ? ((difference / previousProcessing) * 100) 
      : 0;

    return {
      currentProcessing,
      previousProcessing,
      difference,
      percentageChange: Math.round(percentageChange * 100) / 100,
      trend: this.determineTrend(difference)
    };
  }

  formatVariation(value: number): string {
    const sign = value >= 0 ? '+' : '';
    return `${sign}${value.toFixed(2)}%`;
  }

  getTrendClass(trend: 'up' | 'down' | 'stable'): string {
    switch (trend) {
      case 'up':
        return 'text-success';
      case 'down':
        return 'text-danger';
      case 'stable':
        return 'text-secondary';
      default:
        return '';
    }
  }

  getTrendIcon(trend: 'up' | 'down' | 'stable'): string {
    switch (trend) {
      case 'up':
        return 'trending-up';
      case 'down':
        return 'trending-down';
      case 'stable':
        return 'minus';
      default:
        return '';
    }
  }

  private determineTrend(difference: number): 'up' | 'down' | 'stable' {
    if (difference > 0) return 'up';
    if (difference < 0) return 'down';
    return 'stable';
  }
}
