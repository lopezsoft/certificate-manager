import { Injectable } from '@angular/core';
import { HttpEvent, HttpHandler, HttpInterceptor, HttpRequest, HTTP_INTERCEPTORS } from '@angular/common/http';
import { Observable } from 'rxjs';

import TokenService from '../utils/token.service';

@Injectable()
export default class AuthInterceptor implements HttpInterceptor {

  constructor(public api: TokenService) {}

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {

    const token       = this.api.getToken();
    let stringToken   = "*";
    if (token) {
      stringToken = `Bearer ${token.access_token}`;
    }

    const req1 = req.clone({
      headers: req.headers.set('Authorization', stringToken),
    });

    return next.handle(req1);
  }

}


/** Http interceptor providers in outside-in order */
export const httpInterceptorProviders = [
  { provide: HTTP_INTERCEPTORS, useClass: AuthInterceptor, multi: true },
];
