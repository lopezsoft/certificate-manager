import { HttpClient, HttpHeaders } from '@angular/common/http';
import {AccessToken, JsonResponse} from '../../interfaces';
import {BranchOffice} from '../../models/companies-model';
import {environment} from '../../../environments/environment';

export abstract class BaseApiService {
  protected abstract baseUrl: string; // A definir en la clase hija
  protected version: string; // Por si quieres exponer la versión
  public abstract currentToken: AccessToken;

  protected constructor(
    protected http: HttpClient,
  ) {
    this.version = environment.VERSION;
  }

  protected getHeaders(): HttpHeaders {
    return new HttpHeaders({ timeout: `${36000}`, keepalive: 'true' })
      .set('Accept', 'application/json')
      .set('X-App-Version', this.version || '');
  }

  protected geOptions(params: any = {}) {
    return {
      headers         : this.getHeaders(),
      withCredentials : true,
      keepalive       : true,
      params
    };
  }

  private getBodyOptions(body: any = {}) {
    const currentBranchOffice: BranchOffice = JSON.parse(localStorage.getItem('currentBranchOffice'));
    if (body instanceof FormData) {
      body.append('companyId', `${this.currentToken?.currentCompany?.id ?? 0}`);
      if (currentBranchOffice) {
        body.append('branch_id', `${currentBranchOffice.id}`);
      }
    } else {
      body  = {
        ...body,
        companyId: this.currentToken?.currentCompany?.id ?? 0
      };
      if (currentBranchOffice) {
        body  = {
          ...body,
          branch_id: currentBranchOffice.id
        };
      }
    }
    return body;
  }
  private getExtraParams(exParams: any = {}) {
    exParams  = {
      ...exParams,
      companyId: this.currentToken?.currentCompany?.id ?? 0
    };
    const currentBranchOffice: BranchOffice = JSON.parse(localStorage.getItem('currentBranchOffice'));
    if (currentBranchOffice) {
      exParams  = {
        ...exParams,
        branch_id: currentBranchOffice.id
      };
    }
    return exParams;
  }

  delete(query: string, params: any = {}) {
    params  = this.getExtraParams(params);
    return this.http.delete<JsonResponse>(`${this.baseUrl}${query}`, this.geOptions(params));
  }

  post(query: string, body: any = {}) {
    body  = this.getBodyOptions(body);
    return this.http.post<JsonResponse>(`${this.baseUrl}${query}`, body, this.geOptions());
  }

  put(query: string, body: any = {}) {
    body  = this.getBodyOptions(body);
    return this.http.put<JsonResponse>(`${this.baseUrl}${query}`, body, this.geOptions());
  }

  get(query: string, exParams: any = {}) {
    exParams  = this.getExtraParams(exParams);
    return this.http.get<JsonResponse>(`${this.baseUrl}${query}`, this.geOptions(exParams));
  }

  openDocument(url: string) {
    window.open(url, '_blank');
  }

  toQueryString(query: any) {
    return Object.keys(query).map(key => key + '=' + query[key]).join('&');
  }
}
