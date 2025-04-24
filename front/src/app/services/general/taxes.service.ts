import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from './../../interfaces';


import { HttpResponsesService } from '../../utils/http-responses.service';
import { Taxes  } from './../../models/general-model';
@Injectable({
  providedIn: 'root'
})
export class TaxesService {
  data: Taxes[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any): Observable<Taxes[]> {
    const ts  = this;
    return ts.api.get('/taxes', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }
}
