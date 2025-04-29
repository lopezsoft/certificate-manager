import { Component, OnInit } from '@angular/core';
import {SettingsService} from "../services/settings.service";
import TokenService from "../utils/token.service";
import {DashboardService} from "../services/dashboard.service";
import {
    ConsumeForCompany,
    ConsumeForCompanyByMonth,
    ConsumeForCustomer,
    ConsumeForCustomerByMonth
} from "../models/dashboard-model";
import {FormatsService} from "../services/formats.service";

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  protected forCompany: ConsumeForCompany[] = [];
  protected forCompanyByMonth: ConsumeForCompanyByMonth[] = [];
  protected forCustomers: ConsumeForCustomer[] = [];
  protected forCustomersByMonth: ConsumeForCustomerByMonth[] = [];
  toggle: boolean = false;
  protected selectedYear = new Date().getFullYear();
  protected years: number[] = [];
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
    for (let i = 0; i < 4; i++) {
      this.years.push(currentYear - i);
    }

    if (this._token.isAuthenticated()){
      const year = new Date().getFullYear();
      this.getConsumeDocuments(year);
    }
  }


  getConsumeDocuments(year: number) {
      // Get the last 4 years
  }

}
