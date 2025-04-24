import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { HttpResponsesService } from '../../utils';
import { Software} from '../../models/general-model';
import {DataRecords} from "../../interfaces";

@Injectable({
  providedIn: 'root'
})
export class SoftwareService {
  public data: Software[] = [];
  public softwareRecords: DataRecords;
  public currentSoftware: Software;
  constructor(
    private api: HttpResponsesService
  ){}

  getNumberingRange(params: any = {}) {
    const ts  = this;
    return ts.api.get('/numbering-range', params)
      .pipe( map ( (resp ) => {
        return resp;
      }));
  }

  getData(params: any = {}): Observable<Software[]> {
    const ts  = this;
    return ts.api.get('/software', params)
      .pipe( map ( (resp: any ) => {
        this.softwareRecords = resp;
        this.data = resp.dataRecords.data;
        return resp.dataRecords.data;
      }));
  }
}
