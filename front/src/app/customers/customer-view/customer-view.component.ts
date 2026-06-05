import { Component, OnDestroy } from '@angular/core';
import { FormatsService } from '../../services/formats.service';
import { HttpResponsesService } from '../../utils';
import { CustomerService } from '../../services/companies/customers.service';
import { Company } from '../../models/companies-model';
import { CertificateRequestStats, YearlyStats } from '../../models/certificate-stats.model';
import { DocumentStatusDescription } from '../../common/enums/DocumentStatus';
import { animate, style, transition, trigger } from '@angular/animations';
import { Subscription } from 'rxjs';
import { ApexOptions } from 'ng-apexcharts';

/**
 * Colores extraídos de document-status.styles.scss — se usan SOLO para los charts.
 * Los badges usan las clases CSS existentes `document-status-{STATUS}`.
 */
const STATUS_CHART_COLORS: { [key: string]: string } = {
  DRAFT: '#7C7C7C',
  SENT: '#2980B9',
  PENDING: '#F39C12',
  ACCEPTED: '#10572d',
  PROCESSING: '#F39C12',
  PROCESSED: '#085d2c',
  REJECTED: '#D35400',
};

const DEFAULT_CHART_COLOR = '#82868b';

@Component({
    selector: 'app-customer-view',
    templateUrl: './customer-view.component.html',
    styleUrl: './customer-view.component.scss',
    animations: [
        trigger('fadeInOut', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(8px)' }),
                animate('300ms ease-out', style({ opacity: 1, transform: 'translateY(0)' })),
            ]),
            transition(':leave', [
                animate('200ms ease-in', style({ opacity: 0 })),
            ])
        ])
    ],
    standalone: false
})
export class CustomerViewComponent implements OnDestroy {

  /** Mapa de labels en español para cada status */
  protected readonly statusDescription = DocumentStatusDescription;

  stats: CertificateRequestStats | null = null;
  loadingStats = false;
  statsError = false;

  barChartOptions: ApexOptions = {};
  donutChartOptions: ApexOptions = {};
  selectedYear: YearlyStats | null = null;

  private statsSub: Subscription | null = null;

  constructor(
    public format: FormatsService,
    protected http: HttpResponsesService,
    public customer: CustomerService,
  ) {}

  public get currentCustomer(): Company {
    return this.customer.currentCustomer;
  }

  /**
   * Invocado desde el padre (CustomersComponent) al seleccionar un cliente.
   */
  loadStats(companyId: number): void {
    this.statsError = false;
    this.loadingStats = true;
    this.stats = null;
    this.selectedYear = null;

    this.statsSub?.unsubscribe();
    this.statsSub = this.customer.getStats(companyId).subscribe({
      next: (data) => {
        this.stats = data;
        this.loadingStats = false;
        this.buildBarChart(data);
        if (data?.data?.length) {
          this.selectYear(data.data[0]);
        }
      },
      error: () => {
        this.loadingStats = false;
        this.statsError = true;
      },
    });
  }

  selectYear(year: YearlyStats): void {
    this.selectedYear = year;
    this.buildDonutChart(year);
  }

  /** Retorna las entradas del mapa de statuses como array */
  getStatusEntries(): { key: string; value: number; label: string }[] {
    if (!this.selectedYear?.statuses) { return []; }
    return Object.entries(this.selectedYear.statuses).map(([key, value]) => ({
      key,
      value,
      label: this.statusDescription[key] || key,
    }));
  }

  ngOnDestroy(): void {
    this.statsSub?.unsubscribe();
  }

  // ─── Chart Builders ──────────────────────────────────────────

  private buildBarChart(data: CertificateRequestStats): void {
    const years = data.data.map((d) => String(d.year));
    const totals = data.data.map((d) => d.total);

    this.barChartOptions = {
      series: [{ name: 'Total Solicitudes', data: totals }],
      chart: {
        type: 'bar',
        height: 240,
        toolbar: { show: false },
        fontFamily: 'inherit',
        events: {
          dataPointSelection: (_e: any, _chart: any, opts: any) => {
            const idx = opts.dataPointIndex;
            if (data.data[idx]) {
              this.selectYear(data.data[idx]);
            }
          },
        },
      },
      plotOptions: {
        bar: {
          borderRadius: 4,
          columnWidth: '50%',
        },
      },
      colors: ['#2556a3'],
      dataLabels: {
        enabled: true,
        style: { fontSize: '12px', fontWeight: 600 },
      },
      xaxis: {
        categories: years,
        labels: { style: { fontSize: '12px', colors: '#6e6b7b' } },
        axisBorder: { show: false },
      },
      yaxis: {
        labels: { style: { fontSize: '11px', colors: '#6e6b7b' } },
      },
      legend: { show: false },
      grid: {
        strokeDashArray: 4,
        borderColor: '#ebe9f1',
        padding: { top: -15, bottom: -10 },
      },
      tooltip: {
        y: { formatter: (val: number) => `${val} solicitudes` },
      },
    };
  }

  private buildDonutChart(year: YearlyStats): void {
    const labels = Object.keys(year.statuses).map(
      (key) => this.statusDescription[key] || key
    );
    const series = Object.values(year.statuses);
    const colors = Object.keys(year.statuses).map(
      (key) => STATUS_CHART_COLORS[key] || DEFAULT_CHART_COLOR
    );

    this.donutChartOptions = {
      series,
      chart: {
        type: 'donut',
        height: 260,
        fontFamily: 'inherit',
      },
      labels,
      colors,
      legend: {
        position: 'bottom',
        fontSize: '12px',
      },
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
                show: true,
                label: 'Total',
                fontSize: '13px',
                color: '#6e6b7b',
                formatter: (w: any) =>
                  w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0).toString(),
              },
            },
          },
        },
      },
      responsive: [
        { breakpoint: 576, options: { chart: { height: 220 } } },
      ],
    };
  }
}
