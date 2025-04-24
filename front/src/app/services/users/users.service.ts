import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { HttpResponsesService } from '../../utils';

import { Users, UserTypes } from '../../models/users-model';
@Injectable({
  providedIn: 'root'
})
export class UsersService {
  constructor(
    private api: HttpResponsesService
  ){}

  getUserTypes(): Observable<UserTypes[]> {
    const ts  = this;
    return ts.api.get(`/profile/types`)
      .pipe( map ( (resp ) => {
        return resp.records;
      }));
  }

  getProfile(): Observable<Users[]> {
    const ts  = this;
    return ts.api.get(`/profile`)
      .pipe( map ( (resp: any ) => {
        return resp.dataRecords.data;
      }));
  }

  getData(params: any): Observable<Users[]> {
    const ts  = this;
    return ts.api.get(`/profile`, params)
      .pipe( map ( (resp: any ) => {
        return resp.records.data;
      }));
  }
}
