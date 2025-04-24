import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from './../../interfaces';

import { TaxLevel } from './../../models/general-model';

import { HttpResponsesService } from '../../utils/http-responses.service';

@Injectable({
  providedIn: 'root'
})
export class TaxLevelService {
  constructor(
    private api: HttpResponsesService
  ) { }

  getData(params: any): Observable<TaxLevel[]> {
    const ts = this;
    return ts.api.get('/taxlevel', params)
      .pipe(map((resp: JsonResponse) => {
        return resp.records;
      }));
  }
}
