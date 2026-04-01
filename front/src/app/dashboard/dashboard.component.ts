import { Component, OnInit, OnDestroy } from '@angular/core';
import {SettingsService} from "../services/settings.service";
import TokenService from "../utils/token.service";
import {DashboardService} from "../services/dashboard.service";
import {FormatsService} from "../services/formats.service";
import {DocumentStatusDescription, DocumentStatusEnum} from "../common/enums/DocumentStatus";
import {ChartDataTransformerService, MonthlyTrendData} from "./services/chart-data-transformer.service";
import {DashboardMetricsService, DashboardKPIs} from "./services/dashboard-metrics.service";
import {DataExportService} from "./services/data-export.service";
import {ChartConfigurationService} from "./services/chart-configuration.service";
import {DashboardFilterService, FilterOptions} from "./services/dashboard-filter.service";
import {TemporalComparisonService, YearComparison, CompanyComparison} from "./services/temporal-comparison.service";
import {AutoRefreshService, RefreshConfig} from "./services/auto-refresh.service";
import {ConsumeByYear} from "../models/dashboard-model";
import {Subject} from "rxjs";
import {takeUntil} from "rxjs/operators";

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit, OnDestroy {
  toggle: boolean = false;
  protected selectedYear = new Date().getFullYear();
  protected years: number[] = [];
  protected selectedMonth = new Date().getMonth() + 1;
  protected readonly documentStatusDescription = DocumentStatusDescription;
  protected readonly documentStatusEnum = DocumentStatusEnum;
  
  // Nuevas propiedades para funcionalidades v1.4.0
  private destroy$ = new Subject<void>();
  protected yearlyKPIs: DashboardKPIs | null = null;
  protected monthlyKPIs: DashboardKPIs | null = null;
  protected filters: FilterOptions = { searchText: '', status: 'all', lifespan: 'all' };
  protected filteredYearlyData: ConsumeByYear[] = [];
  protected trendChartOptions: any = null;
  protected yearComparison: YearComparison | null = null;
  protected companyComparison: CompanyComparison | null = null;
  protected previousYearData: ConsumeByYear[] = [];
  protected refreshConfig: RefreshConfig = { enabled: false, intervalSeconds: 300 };
  protected countdown: string = '0:00';
  protected availableLifespans: number[] = [];
  
  protected months = [
    {
      name: 'todos',
      value: 0
    },
    {
      name: 'Enero',
      value: 1
    },
    {
      name: 'Febrero',
      value: 2
    },
    {
      name: 'Marzo',
      value: 3
    },
    {
      name: 'Abril',
      value: 4
    },
    {
      name: 'Mayo',
      value: 5
    },
    {
      name: 'Junio',
      value: 6
    },
    {
      name: 'Julio',
      value: 7
    },
    {
      name: 'Agosto',
      value: 8
    },
    {
      name: 'Septiembre',
      value: 9
    },
    {
      name: 'Octubre',
      value: 10
    },
    {
      name: 'Noviembre',
      value: 11
    },
    {
      name: 'Diciembre',
      value: 12
    }
  ];
  constructor(
    public _settings: SettingsService,
    public _token: TokenService,
    public dbs: DashboardService,
    public ft: FormatsService,
    private chartTransformer: ChartDataTransformerService,
    private metricsService: DashboardMetricsService,
    private exportService: DataExportService,
    private chartConfig: ChartConfigurationService,
    private filterService: DashboardFilterService,
    private comparisonService: TemporalComparisonService,
    private autoRefresh: AutoRefreshService
  ) { }

  ngOnInit(): void {
    this._settings.getSettings();

    // Get the last 4 years
    const currentYear = new Date().getFullYear();
    for (let i = 0; i < 2; i++) {
      this.years.push(currentYear - i);
    }

    if (this._token.isAuthenticated()){
      const year = new Date().getFullYear();
      this.getConsumeDocuments(year, this.selectedMonth);
      this.loadPreviousYearData(year - 1);
    }

    // Suscribirse al auto-refresh
    this.autoRefresh.getRefreshTrigger$()
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => this.refreshDashboardData());

    this.autoRefresh.getConfig$()
      .pipe(takeUntil(this.destroy$))
      .subscribe(config => {
        this.refreshConfig = config;
      });

    // Suscribirse al countdown
    this.autoRefresh.getCountdown$()
      .pipe(takeUntil(this.destroy$))
      .subscribe(countdown => {
        if (this.refreshConfig.enabled && countdown > 0) {
          const minutes = Math.floor(countdown / 60);
          const seconds = countdown % 60;
          this.countdown = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        } else {
          this.countdown = '0:00';
        }
      });
  }


  protected getConsumeDocuments(year: number, month: number) {
      // Cargar datos del año
      this.dbs.http.get(`/consume/${year}`)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: (response: any) => {
            this.dbs.consumeByYear = response.data || [];
            this.applyFilters();
          },
          error: (error) => {
            console.error('Error cargando datos anuales:', error);
          }
        });

      // Cargar datos del mes (para la tabla)
      this.dbs.http.get(`/consume/${year}/${month}`)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: (response: any) => {
            this.dbs.consumeByYearAndMonth = response.data || [];
            if (month > 0) {
              this.calculateMonthlyKPIsFromData(this.dbs.consumeByYearAndMonth);
            } else {
              this.monthlyKPIs = null;
            }
          },
          error: (error) => {
            console.error('Error cargando datos mensuales:', error);
          }
        });

      // Cargar datos de TODOS los meses para el gráfico de tendencias
      this.dbs.http.get(`/consume/${year}/0`)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: (response: any) => {
            const allMonthsData = response.data || [];
            this.updateTrendChart(allMonthsData);
          },
          error: (error) => {
            console.error('Error cargando datos para gráfico:', error);
          }
        });
  }

  protected getTotalByYearAndMonth() {
    return this.dbs.consumeByYearAndMonth.reduce((acc, curr) => {
      return acc + curr.total;
    }, 0);
  }

  protected getTotalByYear() {
    const data = this.hasActiveFilters() ? this.filteredYearlyData : this.dbs.consumeByYear;
    return data.reduce((acc, curr) => {
      return acc + curr.total;
    }, 0);
  }

  // Nuevos métodos para funcionalidades v1.4.0

  protected loadPreviousYearData(year: number): void {
    this.dbs.http.get(`/consume/${year}`)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response: any) => {
          this.previousYearData = response.data || [];
          this.calculateComparisons();
        },
        error: (error) => console.error('Error cargando datos del año anterior:', error)
      });
  }

  protected calculateYearlyKPIs(): void {
    if (this.dbs.consumeByYear && this.dbs.consumeByYear.length > 0) {
      this.yearlyKPIs = this.metricsService.calculateKPIs(this.dbs.consumeByYear);
    }
  }

  protected calculateMonthlyKPIs(): void {
    if (this.dbs.consumeByYearAndMonth && this.dbs.consumeByYearAndMonth.length > 0) {
      this.monthlyKPIs = this.metricsService.calculateKPIs(this.dbs.consumeByYearAndMonth);
    }
  }

  protected calculateMonthlyKPIsFromData(data: any[]): void {
    if (data && data.length > 0) {
      this.monthlyKPIs = this.metricsService.calculateKPIs(data);
    }
  }

  protected applyFilters(): void {
    if (this.dbs.consumeByYear) {
      this.filteredYearlyData = this.filterService.filterYearlyData(
        this.dbs.consumeByYear, 
        this.filters
      );
      
      // Actualizar vigencias disponibles
      this.availableLifespans = this.filterService.getUniqueLifespans(this.dbs.consumeByYear);
      
      // Recalcular KPIs con datos filtrados
      if (this.filteredYearlyData.length > 0) {
        this.yearlyKPIs = this.metricsService.calculateKPIs(this.filteredYearlyData);
      } else {
        this.yearlyKPIs = this.metricsService.calculateKPIs(this.dbs.consumeByYear);
      }
    }
  }

  protected clearFilters(): void {
    this.filters = this.filterService.resetFilters();
    this.applyFilters();
  }

  protected hasActiveFilters(): boolean {
    return this.filterService.hasActiveFilters(this.filters);
  }

  protected calculateComparisons(): void {
    if (this.dbs.consumeByYear && this.previousYearData.length > 0) {
      this.yearComparison = this.comparisonService.compareYears(
        this.dbs.consumeByYear,
        this.previousYearData,
        this.selectedYear
      );

      this.companyComparison = this.comparisonService.compareActiveCompanies(
        this.dbs.consumeByYear,
        this.previousYearData
      );
    }
  }

  protected updateTrendChart(data?: any[]): void {
    const chartData = data || this.dbs.consumeByYearAndMonth;
    if (chartData && chartData.length > 0) {
      const trendData = this.chartTransformer.groupByMonth(chartData);
      
      const months = trendData.map(d => d.month);
      const processed = trendData.map(d => d.processed);
      const processing = trendData.map(d => d.processing);
      
      if (!this.trendChartOptions) {
        this.trendChartOptions = this.chartConfig.getTrendLineChartConfig();
      }
      
      this.trendChartOptions = this.chartConfig.updateLineChartData(
        this.trendChartOptions,
        months,
        [
          { name: 'Certificados Procesados', data: processed },
          { name: 'En Proceso', data: processing }
        ]
      );
    }
  }

  protected toggleAutoRefresh(): void {
    this.autoRefresh.toggle();
  }

  protected changeRefreshInterval(seconds: number): void {
    this.autoRefresh.changeInterval(seconds);
  }

  protected refreshDashboardData(): void {
    this.getConsumeDocuments(this.selectedYear, this.selectedMonth);
    this.loadPreviousYearData(this.selectedYear - 1);
  }

  protected exportYearlyData(format: 'csv' | 'json' | 'excel'): void {
    const data = this.filteredYearlyData.length > 0 
      ? this.filteredYearlyData 
      : this.dbs.consumeByYear;

    const columns = [
      { key: 'company_name', label: 'Empresa' },
      { key: 'total', label: 'Total Certificados' },
      { key: 'request_status', label: 'Estado' }
    ];

    const filename = `certificados-${this.selectedYear}`;

    switch (format) {
      case 'csv':
        this.exportService.exportToCSV(data, columns, { filename });
        break;
      case 'json':
        this.exportService.exportToJSON(data, filename);
        break;
      case 'excel':
        this.exportService.exportToExcel(data, columns, { filename });
        break;
    }
  }

  protected exportMonthlyData(format: 'csv' | 'json' | 'excel'): void {
    const data = this.dbs.consumeByYearAndMonth;

    const columns = [
      { key: 'company_name', label: 'Empresa' },
      { key: 'nyear', label: 'Año' },
      { key: 'monthname', label: 'Mes' },
      { key: 'total', label: 'Total Certificados' },
      { key: 'life', label: 'Vigencia (años)' },
      { key: 'request_status', label: 'Estado' }
    ];

    const monthName = this.selectedMonth > 0 
      ? this.months.find(m => m.value === this.selectedMonth)?.name || 'todos'
      : 'todos';
    const filename = `certificados-${this.selectedYear}-${monthName}`;

    switch (format) {
      case 'csv':
        this.exportService.exportToCSV(data, columns, { filename });
        break;
      case 'json':
        this.exportService.exportToJSON(data, filename);
        break;
      case 'excel':
        this.exportService.exportToExcel(data, columns, { filename });
        break;
    }
  }

  protected getTrendClass(trend: string): string {
    return this.comparisonService.getTrendClass(trend as any);
  }

  protected getTrendIcon(trend: string): string {
    return this.comparisonService.getTrendIcon(trend as any);
  }

  protected formatVariation(value: number): string {
    return this.comparisonService.formatVariation(value);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.autoRefresh.stop();
  }
}
