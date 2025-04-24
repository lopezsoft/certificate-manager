import { Injectable } from '@angular/core';
import {map} from 'rxjs/operators';
import {DataRecords, JsonResponse} from '../interfaces';
import {Observable} from 'rxjs';
import {HttpResponsesService} from "../utils";

@Injectable({
  providedIn: 'root'
})
export class CrudTableService {

  constructor(
    protected http: HttpResponsesService
  ) { }
  update(uuid: number, data: any): Observable<JsonResponse> {
    const ts  = this;
    return ts.http.put(`/crud/${uuid}`, data)
      .pipe(
        map ( (resp: JsonResponse ) => {
          return resp;
        })
      );
  }
  create(data: any): Observable<JsonResponse> {
    const ts  = this;
    return ts.http.post(`/crud`, data)
      .pipe(
        map ( (resp: JsonResponse ) => {
          return resp;
        })
      );
  }
  getDataById(uuid: number, params: any = {}): Observable<DataRecords> {
    const ts  = this;
    return ts.http.get(`/crud/${uuid}`, params)
      .pipe(
        map ( (resp: JsonResponse ) => {
          return resp.dataRecords;
        })
      );
  }
  getData(params: any = {}): Observable<DataRecords> {
    const ts  = this;
    return ts.http.get(`/crud`, params)
      .pipe(
        map ( (resp: JsonResponse ) => {
          return resp.dataRecords;
        })
      );
  }
  delete(uuid: number, params: any = {}): Observable<JsonResponse> {
    const ts  = this;
    return ts.http.delete(`/crud/${uuid}`, params)
      .pipe(
        map ( (resp: JsonResponse ) => {
          return resp;
        })
      );
  }
}
