import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';

import { TypeOrganization } from '../../models/companies-model';
import { TaxLevel, TaxRegime, IdentityDocuments
} from '../../models/general-model';
import { HttpResponsesService } from '../../utils';

@Injectable({
  providedIn: 'root'
})
export class DocumentsService {
  constructor(
    private api: HttpResponsesService
  ) { }
  getTypeOrganization(params: any = {}): Observable<TypeOrganization[]> {
    const ts  = this;
    return ts.api.get('/organization-type', params)
      .pipe( map ( (resp: any ) => {
        return resp.dataRecords.data;
      }));
  }

  getIdentityDocuments(params: any = {}): Observable<IdentityDocuments[]> {
    const ts = this;
    return ts.api.get('/identity-documents', params)
      .pipe(map((resp: any) => {
        return resp.dataRecords.data;
      }));
  }

  getTaxLevel(params: any = {}): Observable<TaxLevel[]> {
    const ts = this;
    return ts.api.get('/fiscal-regime', params)
      .pipe(map((resp: JsonResponse) => {
        return resp.dataRecords.data;
      }));
  }

  getTaxRegime(params: any = {}): Observable<TaxRegime[]> {
    const ts = this;
    return ts.api.get('/accounting-regime', params)
      .pipe(map((resp: JsonResponse) => {
        return resp.dataRecords.data;
      }));
  }
}
