import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';

import { IdentityDocuments } from '../../models/general-model';

import { HttpResponsesService } from '../../utils';

@Injectable({
  providedIn: 'root'
})
export class IdentityDocumentsService {

  data: IdentityDocuments[] = [];
  constructor(
    private api: HttpResponsesService
  ) { }

  getData(params: any = {}): Observable<IdentityDocuments[]> {
    const ts = this;
    return ts.api.get('/identity-documents', params)
      .pipe(map((resp: JsonResponse) => {
        return resp.dataRecords.data;
      }));
  }
}
