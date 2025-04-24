import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from '../../utils';
import {Currency} from '../../models/general-model';
@Injectable({
  providedIn: 'root'
})
export class CurrencyService {
  data: Currency[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any = {}): Observable<Currency[]> {
    const ts  = this;
    return ts.api.get('/currency/all', params)
      .pipe( map ( (resp ) => {
        return resp.dataRecords.data;
      }));
  }
}
