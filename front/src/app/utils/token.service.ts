import { Injectable } from '@angular/core';
import { User } from '../auth/models';
import { AccessToken } from '../interfaces';
import { HttpResponsesService } from './http-responses.service';

@Injectable({
  providedIn: 'root'
})
export default class TokenService {
  public currentUser !: User;
  constructor(private _http: HttpResponsesService) {
  }

  isAuthenticated(): boolean {
    const
      token = this.getToken();
    return !!(token);
  }

  isAdmin(): boolean {
    const
      token = this.getToken();
    const user = token.user;
    return (user && (user.type_id === 1));
  }

  getToken(): AccessToken {
    const ts  = this;
    const ls  = localStorage.getItem(ts._http.getApiJwt());
    let
      token: AccessToken;
      token = (ls) ? JSON.parse(ls) : null;
    return token;
  }

    getCurrentUser() {
    const ts = this;
    let user: any = {};
    /**
     * Set user value
     */
    if (!localStorage.getItem('currentUser')) {
      const token = ts.getToken();
      if (token) {
        user.avatar       = token.user.avatarUrl
        user.email        = token.user.mail;
        user.firstName    = token.user.first_name;
        user.lastName     = token.user.last_name;
        user.token        = token.access_token;
        user.id           = token.user.id;
        localStorage.setItem('currentUser', JSON.stringify(user));
      }
    }

    ts.currentUser = JSON.parse(localStorage.getItem('currentUser') || '');
    return ts.currentUser;
  }

  upCurrentUser(data: User) {
    const ts = this;
    let user: any = {};
    user.avatar = `${data.avatar}`;
    user.email = data.email;
    user.firstName = data.firstName;
    user.lastName = data.lastName;
    user.token = ts.currentUser.token;
    user.id = data.id;
    localStorage.removeItem('currentUser');
    localStorage.setItem('currentUser', JSON.stringify(user));
    ts.currentUser = JSON.parse(localStorage.getItem('currentUser') || '');
    return ts.currentUser;
  }

  onClearCurrentUser() {
    localStorage.removeItem('currentUser');
  }
}
