import { Injectable } from '@angular/core';
import {BaseApiService} from './base-api.service';
import {AccessToken} from '../../interfaces';
import {HttpClient} from '@angular/common/http';
import {AccessTokenService} from '../singletons/access-token.service';
import {environment} from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class NestApiServerService extends BaseApiService {
  // Sobrescribimos baseUrl y version para BaseApiService
  public currentToken: AccessToken;
  protected baseUrl: string;
  constructor(
    protected http: HttpClient,
    public tokenService: AccessTokenService,
  ) {
    super(http, tokenService);
    this.baseUrl = environment.NESTJS_API; // URL base para NESTJS_API
    this.currentToken = this.tokenService.getToken();
  }
  delete(query: string, params: any = {}) {
    return super.delete(query, params);
  }

  post(query: string, body: any = {}) {
    return super.post(query, body);
  }

  put(query: string, body: any = {}) {
    return super.put(query, body);
  }

  get(query: string, exParams: any = {}) {
    return super.get(query, exParams);
  }

}
