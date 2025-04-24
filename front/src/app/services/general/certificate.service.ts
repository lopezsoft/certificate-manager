import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { HttpResponsesService } from '../../utils';
import { Certificate} from '../../models/general-model';
@Injectable({
  providedIn: 'root'
})
export class CertificateService {
  data: Certificate[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(params: any): Observable<Certificate> {
    const ts  = this;
    return ts.api.get('/certificate', params)
      .pipe( map ( (resp: any ) => {
        return resp.dataRecords.data[0];
      }));
  }
}
