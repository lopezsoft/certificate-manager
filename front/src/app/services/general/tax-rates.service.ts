import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from './../../interfaces';

import { HttpResponsesService } from '../../utils/http-responses.service';
import { TaxRates } from './../../models/general-model'

@Injectable({
  providedIn: 'root'
})
export class TaxRatesService {

  data: TaxRates[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any): Observable<TaxRates[]> {
    const ts  = this;
    return ts.api.get('/settings/taxerates/read', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }

}
