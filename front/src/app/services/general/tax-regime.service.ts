import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';

import { TaxRegime } from '../../models/general-model';

import { HttpResponsesService } from '../../utils';

@Injectable({
  providedIn: 'root'
})
export class TaxRegimeService {
  constructor(
    private api: HttpResponsesService
  ) { }

  getData(params: any): Observable<TaxRegime[]> {
    const ts = this;
    return ts.api.get('/taxregime', params)
      .pipe(map((resp: JsonResponse) => {
        return resp.records;
      }));
  }
}

