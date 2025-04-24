import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { HttpResponsesService } from '../../utils';
import { DestinationEnvironment} from '../../models/general-model';
@Injectable({
  providedIn: 'root'
})
export class DestinationEnvironmentService {
  data: DestinationEnvironment[] = [];
  constructor(
    private api: HttpResponsesService
  ){}

  getData(): Observable<DestinationEnvironment[]> {
    const ts  = this;
    return ts.api.get('/destination-environment')
      .pipe( map ( (resp ) => {
        return resp.dataRecords.data;
      }));
  }
}
