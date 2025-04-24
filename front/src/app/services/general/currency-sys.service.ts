import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';
import { HttpResponsesService } from '../../utils';
import { CurrencyChange, CurrencySys } from '../../models/general-model';

@Injectable({
  providedIn: 'root'
})
export class CurrencySysService {
  data: CurrencySys[] = [];
  constructor(
    private api: HttpResponsesService
  ) { }

  getData(params: any = {}): Observable<CurrencySys[]> {
    return this.api.get('/currency', params)
      .pipe(map((resp: any) => {
        return resp.dataRecords.data;
      }));
  }

  getChangeLocal(params: any): Observable<CurrencyChange[]> {
    return this.api.get('/currency/change/local', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }
}

