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

        // Solo limpiar sesión y redirigir al login si el usuario TENÍA una sesión activa
        // (había un token) pero el servidor la rechazó. NO aplica en rutas públicas
        // como /register o /login donde no hay token.
        if ([401, 403].indexOf(error.status) !== -1) {
          if (_auth?.isAuthenticated()) {
            // Sesión expirada o sin permisos — limpiar y redirigir
            this.clearSessionData();
          } else {
            // Ruta pública con credenciales inválidas — solo mostrar error
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

