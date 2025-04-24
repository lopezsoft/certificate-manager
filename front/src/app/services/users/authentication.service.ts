import { Injectable } from '@angular/core';

import { User, Role } from '../../auth/models';

import  TokenService  from '../../utils/token.service';

@Injectable({ providedIn: 'root' })
export class AuthenticationService {

  /**
   *
   * @param {TokenService} _api
   */
  constructor(
    private _api: TokenService,
  ) {}

  // getter: currentUserValue
  public get currentUserValue(): User {
    return this._api.getCurrentUser();
  }

  /**
   *  Confirms if user is admin
   */
  get isAdmin() {
    const _api  = this._api;
    return _api.isAuthenticated() && _api.getCurrentUser() && _api.getCurrentUser().role === Role.Admin;
  }

  /**
   *  Confirms if user is client
   */
  get isClient() {
    const _api  = this._api;
    return _api.isAuthenticated() && _api.getCurrentUser() && _api.getCurrentUser().role === Role.Client;
  }
}
