import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';


import { HttpResponsesService } from '../../utils';
import { Country } from '../../models/general-model';

@Injectable({
  providedIn: 'root'
})
export class CountriesService {
  data: Country[] = [];
  constructor(
    private api: HttpResponsesService
  ) { }

  getData(): Observable<Country[]> {
    const ts = this;
    return ts.api.get('/countries')
      .pipe(map((resp: JsonResponse) => {
        return resp.dataRecords.data;
      }));
  }
}

