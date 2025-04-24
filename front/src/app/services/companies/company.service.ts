import { Injectable } from '@angular/core';
import { HttpResponsesService } from '../../utils';
import { Company } from '../../models/companies-model';
import { map } from 'rxjs/operators';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class CompanyService {

  data: Company[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any): Observable<Company[]> {
    const ts  = this;
    return ts.api.get('/company', params)
      .pipe( map ( (resp: any ) => {
        return resp.dataRecords.data;
      }));
  }
}
