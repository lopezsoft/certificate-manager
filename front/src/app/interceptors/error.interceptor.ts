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
        const _auth = this.accessToken;
        if (_auth && !_auth?.isAuthenticated()) {
          this.clearSessionData();
        }
        if ([401, 403].indexOf(error.status) !== -1) {
          // auto logout if 401 Unauthorized or 403 Forbidden response returned from api
          this._router.navigate(['/auth/not-authorized']);
        }

        // HTTP 402: Sin cupo disponible — propagar sin mostrar error genérico.
        // Los componentes que llaman al endpoint deben manejar este caso
        // detectando error.status === 402 y redirigiendo al flujo de compra.
        if (error.status === 402) {
          return throwError(() => error);
        }

        // HTTP 429: Rate limit — mensaje amigable
        if (error.status === 429) {
          this.errorService.showError('Demasiadas solicitudes. Intente nuevamente en un minuto.', error.status);
          return throwError(() => error);
        }

        const errorMessage = error.error?.message || error.message;
        this.errorService.showError(errorMessage, error.status);
        return throwError(() => error);
      })
    );
  }

  private clearSessionData() {
    this?.accessToken?.onClearCurrentUser();
    this._router.navigate(['/auth/login']);
  }
}

