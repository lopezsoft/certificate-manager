import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from './../../interfaces';

import { HttpResponsesService } from '../../utils/http-responses.service';
import { Banks } from '../../models/general-model'

@Injectable({
  providedIn: 'root'
})
export class BanksService {

  data: Banks[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any): Observable<Banks[]> {
    const ts  = this;
    return ts.api.get('/settings/banks/read', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }

}
