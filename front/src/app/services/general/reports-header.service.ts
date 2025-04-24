import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import {HttpResponsesService} from '../../utils';
import { ReportsHeader} from '../../models/general-model';
@Injectable({
  providedIn: 'root'
})
export class ReportsHeaderService {
  data: ReportsHeader[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any): Observable<ReportsHeader[]> {
    const ts  = this;
    return ts.api.get('/settings/reports', params)
      .pipe( map ( (resp: any ) => {
        return resp.dataRecords.data;
      }));
  }
}
