import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { HttpResponsesService } from '../../utils';
import { AccountingDocuments } from '../../models/accounting-model';
import { Resolutions} from '../../models/general-model';
@Injectable({
  providedIn: 'root'
})
export class ResolutionsService {
  data: Resolutions[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getAccountingDocuments(params: any): Observable<AccountingDocuments[]> {
    return this.api.get('/document-type', params)
      .pipe( map ( (resp: any) => {
        return resp.dataRecords.data;
      }));
  }

  getData(params: any): Observable<Resolutions[]> {
    return this.api.get('/resolutions', params)
      .pipe( map ( (resp: any) => {
        return resp.dataRecords.data;
      }));
  }
}
