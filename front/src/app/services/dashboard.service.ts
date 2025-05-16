import { Injectable } from '@angular/core';
import {HttpResponsesService} from "../utils";
import {
  ConsumeByYear, ConsumeByYearAndMonth,
} from "../models/dashboard-model";

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  public consumeByYear: ConsumeByYear[] = [];
  public consumeByYearAndMonth: ConsumeByYearAndMonth[] = [];
  constructor(
      public http: HttpResponsesService,
  ) { }

  getByYear(year: number) {
    return this.http.get(`/consume/${year}`)
      .subscribe({
        next: (response: any) => {
          this.consumeByYear = response.data;
        },
        error: (error) => {
          console.error('Error fetching data:', error);
        }
      });
  }

  getByYearAndMonth(year: number, month: number) {
    return this.http.get(`/consume/${year}/${month}`)
      .subscribe({
        next: (response: any) => {
          this.consumeByYearAndMonth = response.data;
        },
        error: (error) => {
          console.error('Error fetching data:', error);
        }
      });
  }
}
