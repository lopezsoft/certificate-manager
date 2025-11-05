import { Injectable } from '@angular/core';
import { ConsumeByYear, ConsumeByYearAndMonth } from '../../models/dashboard-model';
import { DocumentStatusEnum } from '../../common/enums/DocumentStatus';

export interface FilterOptions {
  searchText?: string;
  status?: 'all' | DocumentStatusEnum.PROCESSED | DocumentStatusEnum.PROCESSING;
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

    return filtered;
  }

  hasActiveFilters(filters: FilterOptions): boolean {
    return (
      (filters.searchText && filters.searchText.trim() !== '') ||
      (filters.status && filters.status !== 'all')
    );
  }

  resetFilters(): FilterOptions {
    return {
      searchText: '',
      status: 'all'
    };
  }
}
