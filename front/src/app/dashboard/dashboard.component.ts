import { Component, OnInit } from '@angular/core';
import {SettingsService} from "../services/settings.service";
import TokenService from "../utils/token.service";
import {DashboardService} from "../services/dashboard.service";
import {FormatsService} from "../services/formats.service";
import {DocumentStatusDescription} from "../common/enums/DocumentStatus";

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  toggle: boolean = false;
  protected selectedYear = new Date().getFullYear();
  protected years: number[] = [];
  protected selectedMonth = new Date().getMonth() + 1;
  protected readonly documentStatusDescription = DocumentStatusDescription;
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
    public ft: FormatsService
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
    }
  }


  protected getConsumeDocuments(year: number, month: number) {
      this.dbs.getByYear(year);
      this.dbs.getByYearAndMonth(year, month);
  }

  protected getTotalByYearAndMonth() {
    // @ts-ignore
    return this.dbs.consumeByYearAndMonth.reduce((acc, curr) => {
      return acc + curr.total;
    }, 0);
  }

  protected getTotalByYear() {
    // @ts-ignore
    return this.dbs.consumeByYear.reduce((acc, curr) => {
      return acc + curr.total;
    }, 0);
  }
}
