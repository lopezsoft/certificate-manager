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

        // HTTP 401: Unauthorized — siempre redirigir a not-authorized para que el usuario
        // pueda restablecer acceso (limpiar sesión local y reintentar login).
        if (error.status === 401) {
          this._router.navigate(['/auth/not-authorized']);
          return throwError(() => error);
        }

        // HTTP 403: Forbidden — usuario autenticado pero sin permisos suficientes
        if (error.status === 403) {
          if (_auth?.isAuthenticated()) {
            this.clearSessionData();
          } else {
            this._router.navigate(['/auth/not-authorized']);
          }
        }

        // HTTP 402: Sin cupo disponible — propagar sin mostrar error genérico.
        if (error.status === 402) {
          return throwError(() => error);
        }

        // HTTP 422: Error de validación de formulario — propagar sin mostrar
        // toast genérico para que el componente pueda mapear los errores por campo.
        if (error.status === 422) {
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

