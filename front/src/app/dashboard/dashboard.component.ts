import { Component, OnInit, OnDestroy } from '@angular/core';
import {SettingsService} from "../services/settings.service";
import TokenService from "../utils/token.service";
import {DashboardService} from "../services/dashboard.service";
import {FormatsService} from "../services/formats.service";
import {DocumentStatusDescription, DocumentStatusEnum} from "../common/enums/DocumentStatus";
import {ChartDataTransformerService} from "./services/chart-data-transformer.service";
import {DashboardMetricsService, DashboardKPIs} from "./services/dashboard-metrics.service";
import {DataExportService} from "./services/data-export.service";
import {ChartConfigurationService} from "./services/chart-configuration.service";
import {DashboardFilterService, FilterOptions} from "./services/dashboard-filter.service";
import {TemporalComparisonService, YearComparison, CompanyComparison} from "./services/temporal-comparison.service";
import {AutoRefreshService, RefreshConfig} from "./services/auto-refresh.service";
import {ConsumeByYear} from "../models/dashboard-model";
import {CertificateRequestStats, YearlyStats} from "../models/certificate-stats.model";
import {CustomerService} from "../services/companies/customers.service";
import {Subject} from "rxjs";
import {takeUntil} from "rxjs/operators";
import {ApexOptions} from "ng-apexcharts";

@Component({
    selector: 'app-dashboard',
    templateUrl: './dashboard.component.html',
    styleUrls: ['./dashboard.component.scss'],
    standalone: false
})
export class DashboardComponent implements OnInit, OnDestroy {
  toggle: boolean = false;
  protected selectedYear = new Date().getFullYear();
  protected years: number[] = [];
  protected selectedMonth = new Date().getMonth() + 1;
  protected readonly documentStatusDescription = DocumentStatusDescription;
  protected readonly documentStatusEnum = DocumentStatusEnum;

  // Propiedades v1.4.0
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

  // ── Stats de la empresa en sesión ──
  protected companyStats: CertificateRequestStats | null = null;
  protected companyStatsLoading = false;
  protected companyStatsError = false;
  protected companyBarChart: ApexOptions = {};
  protected companyDonutChart: ApexOptions = {};
  protected companySelectedYear: YearlyStats | null = null;

  protected months = [
    { name: 'todos', value: 0 },
    { name: 'Enero', value: 1 },
    { name: 'Febrero', value: 2 },
    { name: 'Marzo', value: 3 },
    { name: 'Abril', value: 4 },
    { name: 'Mayo', value: 5 },
    { name: 'Junio', value: 6 },
    { name: 'Julio', value: 7 },
    { name: 'Agosto', value: 8 },
    { name: 'Septiembre', value: 9 },
    { name: 'Octubre', value: 10 },
    { name: 'Noviembre', value: 11 },
    { name: 'Diciembre', value: 12 }
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
    private autoRefresh: AutoRefreshService,
    private customerService: CustomerService,
  ) { }

  ngOnInit(): void {
    this._settings.getSettings();

    const currentYear = new Date().getFullYear();
    for (let i = 0; i < 2; i++) {
      this.years.push(currentYear - i);
    }

    if (this._token.isAuthenticated()){
      const year = new Date().getFullYear();
      this.getConsumeDocuments(year, this.selectedMonth);
      this.loadPreviousYearData(year - 1);
      this.loadCompanyStats();
    }

    this.autoRefresh.getRefreshTrigger$()
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => this.refreshDashboardData());

    this.autoRefresh.getConfig$()
      .pipe(takeUntil(this.destroy$))
      .subscribe(config => {
        this.refreshConfig = config;
      });

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
      this.availableLifespans = this.filterService.getUniqueLifespans(this.dbs.consumeByYear);
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
      case 'csv': this.exportService.exportToCSV(data, columns, { filename }); break;
      case 'json': this.exportService.exportToJSON(data, filename); break;
      case 'excel': this.exportService.exportToExcel(data, columns, { filename }); break;
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
      case 'csv': this.exportService.exportToCSV(data, columns, { filename }); break;
      case 'json': this.exportService.exportToJSON(data, filename); break;
      case 'excel': this.exportService.exportToExcel(data, columns, { filename }); break;
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

  // ─── Stats empresa en sesión ───────────────────────────────────

  private loadCompanyStats(): void {
    this.companyStatsLoading = true;
    this.companyStatsError = false;
    this.customerService.getStats(0)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.companyStats = data;
          this.companyStatsLoading = false;
          this.buildCompanyBarChart(data);
          if (data?.data?.length) {
            this.selectCompanyYear(data.data[0]);
          }
        },
        error: () => {
          this.companyStatsLoading = false;
          this.companyStatsError = true;
        },
      });
  }

  protected selectCompanyYear(year: YearlyStats): void {
    this.companySelectedYear = year;
    this.buildCompanyDonutChart(year);
  }

  protected getCompanyStatusEntries(): { key: string; value: number; label: string }[] {
    if (!this.companySelectedYear?.statuses) { return []; }
    return Object.entries(this.companySelectedYear.statuses).map(([key, value]) => ({
      key,
      value,
      label: this.documentStatusDescription[key] || key,
    }));
  }

  private buildCompanyBarChart(data: CertificateRequestStats): void {
    const years = data.data.map((d) => String(d.year));
    const totals = data.data.map((d) => d.total);
    this.companyBarChart = {
      series: [{ name: 'Total Solicitudes', data: totals }],
      chart: {
        type: 'bar', height: 240, toolbar: { show: false }, fontFamily: 'inherit',
        events: {
          dataPointSelection: (_e: any, _chart: any, opts: any) => {
            const idx = opts.dataPointIndex;
            if (data.data[idx]) { this.selectCompanyYear(data.data[idx]); }
          },
        },
      },
      plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
      colors: ['#2556a3'],
      dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 600 } },
      xaxis: {
        categories: years,
        labels: { style: { fontSize: '12px', colors: '#6e6b7b' } },
        axisBorder: { show: false },
      },
      yaxis: { labels: { style: { fontSize: '11px', colors: '#6e6b7b' } } },
      legend: { show: false },
      grid: { strokeDashArray: 4, borderColor: '#ebe9f1', padding: { top: -15, bottom: -10 } },
      tooltip: { y: { formatter: (val: number) => `${val} solicitudes` } },
    };
  }

  private buildCompanyDonutChart(year: YearlyStats): void {
    const statusColors: { [key: string]: string } = {
      DRAFT: '#7C7C7C', SENT: '#2980B9', PENDING: '#F39C12',
      ACCEPTED: '#10572d', PROCESSING: '#F39C12', PROCESSED: '#085d2c', REJECTED: '#D35400',
    };
    const labels = Object.keys(year.statuses).map((k) => this.documentStatusDescription[k] || k);
    const series = Object.values(year.statuses);
    const colors = Object.keys(year.statuses).map((k) => statusColors[k] || '#82868b');
    this.companyDonutChart = {
      series, labels, colors,
      chart: { type: 'donut', height: 260, fontFamily: 'inherit' },
      legend: { position: 'bottom', fontSize: '12px' },
      dataLabels: {
        enabled: true,
        formatter: (val: number) => `${val.toFixed(0)}%`,
        style: { fontSize: '11px' },
      },
      plotOptions: {
        pie: {
          donut: {
            size: '60%',
            labels: {
              show: true,
              name: { show: true, fontSize: '14px', color: '#5e5873' },
              value: { show: true, fontSize: '20px', fontWeight: 700, color: '#5e5873' },
              total: {
                show: true, label: 'Total', fontSize: '13px', color: '#6e6b7b',
                formatter: (w: any) => w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0).toString(),
              },
            },
          },
        },
      },
      responsive: [{ breakpoint: 576, options: { chart: { height: 220 } } }],
    };
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.autoRefresh.stop();
  }
}
