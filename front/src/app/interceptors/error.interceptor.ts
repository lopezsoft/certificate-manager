import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { HttpRequest, HttpHandler, HttpEvent, HttpInterceptor, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';

import {ErrorService} from '../services/error.service';
import TokenService from "../utils/token.service";

@Injectable()
export class ErrorInterceptor implements HttpInterceptor {
  constructor(
    private _router: Router,
    private errorService: ErrorService,
    private accessToken: TokenService
  ) {}
  intercept(request: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    return next.handle(request).pipe(
      catchError((error: HttpErrorResponse) => {
        // console.log('ErrorInterceptor', error);
        const _auth = this.accessToken;
        if (_auth && !_auth?.isAuthenticated()) {
          this.clearSessionData();
        }
        if ([401, 403].indexOf(error.status) !== -1) {
          // auto logout if 401 Unauthorized or 403 Forbidden response returned from api
          this._router.navigate(['/auth/not-authorized']);
        }
        const errorMessage = error.error.message || error.message;
        this.errorService.showError(errorMessage, error.status);
        // throwError
        return throwError(errorMessage);
      })
    );
  }

  private clearSessionData() {
    this?.accessToken?.onClearCurrentUser();
    this._router.navigate(['/auth/login']);
  }
}
