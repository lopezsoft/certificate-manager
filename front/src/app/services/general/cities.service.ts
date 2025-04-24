import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';


import { HttpResponsesService } from '../../utils';
import { Cities } from '../../models/general-model';

@Injectable({
  providedIn: 'root'
})
export class CitiesService {
  data: Cities[] = [];
  constructor(
    private api: HttpResponsesService
  ) { }


  getData(params: any = {}): Observable<Cities[]> {
    const ts = this;
    return ts.api.get('/cities', params)
      .pipe(map((resp: JsonResponse) => {
        return resp.dataRecords.data;
      }));
  }
}

