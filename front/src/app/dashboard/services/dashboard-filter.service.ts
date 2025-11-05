import { Injectable } from '@angular/core';
import { ConsumeByYear, ConsumeByYearAndMonth } from '../../models/dashboard-model';
import { DocumentStatusEnum } from '../../common/enums/DocumentStatus';

export interface FilterOptions {
  searchText?: string;
  status?: 'all' | DocumentStatusEnum.PROCESSED | DocumentStatusEnum.PROCESSING;
  lifespan?: number | 'all';
}

@Injectable({
  providedIn: 'root'
})
export class DashboardFilterService {

  constructor() { }

  filterYearlyData(data: ConsumeByYear[], filters: FilterOptions): ConsumeByYear[] {
    if (!data || data.length === 0) return [];

    let filtered = [...data];

    if (filters.searchText && filters.searchText.trim() !== '') {
      const searchLower = filters.searchText.toLowerCase().trim();
      filtered = filtered.filter(item => 
        item.company_name.toLowerCase().includes(searchLower)
      );
    }

    if (filters.status && filters.status !== 'all') {
      filtered = filtered.filter(item => item.request_status === filters.status);
    }

    if (filters.lifespan && filters.lifespan !== 'all') {
      const lifespanNumber = typeof filters.lifespan === 'string' 
        ? parseInt(filters.lifespan, 10) 
        : filters.lifespan;
      filtered = filtered.filter(item => item.life === lifespanNumber);
    }

    return filtered;
  }

  filterMonthlyData(data: ConsumeByYearAndMonth[], filters: FilterOptions): ConsumeByYearAndMonth[] {
    if (!data || data.length === 0) return [];

    let filtered = [...data];

    if (filters.searchText && filters.searchText.trim() !== '') {
      const searchLower = filters.searchText.toLowerCase().trim();
      filtered = filtered.filter(item => 
        item.company_name.toLowerCase().includes(searchLower)
      );
    }

    if (filters.status && filters.status !== 'all') {
      filtered = filtered.filter(item => item.request_status === filters.status);
    }

    if (filters.lifespan && filters.lifespan !== 'all') {
      const lifespanNumber = typeof filters.lifespan === 'string' 
        ? parseInt(filters.lifespan, 10) 
        : filters.lifespan;
      filtered = filtered.filter(item => item.life === lifespanNumber);
    }

    return filtered;
  }

  getUniqueLifespans(data: ConsumeByYear[]): number[] {
    if (!data || data.length === 0) return [];

    const lifespans = new Set<number>();
    data.forEach(item => {
      if (item.life !== null && item.life !== undefined) {
        lifespans.add(item.life);
      }
    });

    return Array.from(lifespans).sort((a, b) => a - b);
  }

  hasActiveFilters(filters: FilterOptions): boolean {
    return (
      (filters.searchText && filters.searchText.trim() !== '') ||
      (filters.status && filters.status !== 'all') ||
      (filters.lifespan && filters.lifespan !== 'all')
    );
  }

  resetFilters(): FilterOptions {
    return {
      searchText: '',
      status: 'all',
      lifespan: 'all'
    };
  }
}
