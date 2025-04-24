import { Injectable } from '@angular/core';
import {HttpResponsesService} from "../utils";
import {
  ConsumeForCompany,
  ConsumeForCompanyByMonth,
  ConsumeForCustomer,
  ConsumeForCustomerByMonth
} from "../models/dashboard-model";

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  public forCompany: ConsumeForCompany[] = [];
  public forCompanyByMonth: ConsumeForCompanyByMonth[] = [];
  public forCustomers: ConsumeForCustomer[] = [];
  public forCustomersByMonth: ConsumeForCustomerByMonth[] = [];
  constructor(
      public http: HttpResponsesService,
  ) { }

  getConsumeDocuments(params: any = {}) {
    return this.http.get('/documents/consume', params);
  }
}
