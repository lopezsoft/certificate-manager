import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';

import {environment} from '../../environments/environment';
import {JsonResponse} from "../interfaces/json-response.interface";
import {GlobalSettingsService} from "./global-settings.service";

@Injectable({
  providedIn: 'root'
})
export class HttpServerService {
  private url: string;
  private appUrl: string;
  private apiJwt: string;
  constructor(
    private http: HttpClient,
    public _settings: GlobalSettingsService,
  ) {
    this.url    = environment.APIURL;
    this.appUrl = environment.APPURL;
    this.apiJwt = environment.APIJWT;
  }
  private getHeaders(): HttpHeaders {
    return  new HttpHeaders({timeout: `${36000}`, keepalive: 'true'})
      .set('Accept', 'application/json')
      .set('Access-Control-Allow-Origin', '*')
      .set('Access-Control-Allow-Methods', 'GET, POST, DELETE, PUT');
  }
  delete(query: string, params: any = {}) {
    const me = this;
    params  = {
      ...params,
    };
    return me.http.delete<JsonResponse>(`${ me.url }${ query }`, this.geOptions(params));
  }

  post(query: string, body: any = {}) {
    const me = this;
    if (body instanceof FormData) {
      body.append('schoolId', me._settings.schoolParams.schoolId.toString());
      body.append('id', me._settings.schoolParams.id.toString());
      body.append('uuid', me._settings.schoolParams.uuid);
    } else {
      body  = {
        ...body,
        schoolId: me._settings.schoolParams.schoolId,
        id: me._settings.schoolParams.id,
        uuid: me._settings.schoolParams.uuid,
      };
    }
    return me.http.post<JsonResponse>(`${ me.url }${ query }`, body, this.geOptions());
  }

  put(query: string, body: any) {
    const me = this;
    if (body instanceof FormData) {
      body.append('schoolId', me._settings.schoolParams.schoolId.toString());
      body.append('id', me._settings.schoolParams.id.toString());
      body.append('uuid', me._settings.schoolParams.uuid);
    } else {
      body  = {
        ...body,
        schoolId: me._settings.schoolParams.schoolId,
        id: me._settings.schoolParams.id,
        uuid: me._settings.schoolParams.uuid,
      };
    }
    return me.http.put<JsonResponse>(`${ me.url }${ query }`, body, this.geOptions());
  }

  get(query: string, exParams: any = {}) {
    const me = this;
    exParams  = {
      ...exParams,
      schoolId: me._settings.schoolParams.schoolId,
      id: me._settings.schoolParams.id,
      uuid: me._settings.schoolParams.uuid,
    };
    return me.http.get<JsonResponse>(`${me.url}${ query }`, this.geOptions(exParams));
  }

  private geOptions(params: any = {}) {
    return {
      headers         : this.getHeaders(),
      withCredentials : true,
      params
    };
  }
}

