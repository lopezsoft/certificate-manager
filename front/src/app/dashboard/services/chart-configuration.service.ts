import { Injectable } from '@angular/core';
import { ApexOptions } from 'ng-apexcharts';

@Injectable({
  providedIn: 'root'
})
export class ChartConfigurationService {

  private readonly PRIMARY_COLOR = '#7367F0';
  private readonly WARNING_COLOR = '#FF9F43';

  constructor() { }

  getTrendLineChartConfig(): ApexOptions {
    return {
      series: [
        { name: 'Certificados Procesados', data: [] },
        { name: 'En Proceso', data: [] }
      ],
      chart: {
        type: 'line',
        height: 350,
        toolbar: { show: true },
        zoom: { enabled: true }
      },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      colors: [this.PRIMARY_COLOR, this.WARNING_COLOR],
      dataLabels: { enabled: false },
      xaxis: {
        categories: [],
        labels: { rotate: -45, rotateAlways: false }
      },
      yaxis: {
        title: { text: 'Cantidad de Certificados' },
        labels: { formatter: (value) => Math.round(value).toString() }
      },
      legend: { position: 'top', horizontalAlign: 'left' },
      grid: { borderColor: '#e7e7e7', strokeDashArray: 4 },
      tooltip: {
        theme: 'light',
        y: { formatter: (value) => `${value} certificados` }
      }
    };
  }

  updateLineChartData(
    config: ApexOptions,
    categories: string[],
    series: { name: string; data: number[] }[]
  ): ApexOptions {
    return {
      ...config,
      series: series,
      xaxis: {
        ...config.xaxis,
        categories: categories
      }
    };
  }
}
