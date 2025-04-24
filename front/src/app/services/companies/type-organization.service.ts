import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from '../../utils';
import { JsonResponse } from '../../interfaces';


import { TypeOrganization } from '../../models/companies-model';

@Injectable({
  providedIn: 'root'
})
export class TypeOrganizationService {
  data: TypeOrganization[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any = {}): Observable<TypeOrganization[]> {
    const ts  = this;
    return ts.api.get('/organization-type', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.dataRecords.data;
      }));
  }
}

