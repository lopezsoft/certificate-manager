import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot } from '@angular/router';
import TokenService from '../utils/token.service';
import { Role } from '../auth/models';

/**
 * AuthGuard — Protege rutas verificando autenticación y roles.
 *
 * Valida:
 *   1. Que el usuario esté autenticado (token válido)
 *   2. Que el rol del usuario esté dentro de los roles permitidos
 *      definidos en `route.data.roles` (si existen)
 *
 * Si no está autenticado → redirige a /auth/login
 * Si no tiene el rol adecuado → redirige a /auth/not-authorized
 */
@Injectable({
  providedIn: 'root'
})
export default class AuthGuard {

  constructor(
    private authService: TokenService,
    private router: Router,
  ) {}

  canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): boolean {
    if (!this.authService.isAuthenticated()) {
      this.router.navigate(['/auth/login'], { queryParams: { returnUrl: state.url } });
      return false;
    }

    // Si la ruta define roles permitidos, validar contra el rol del usuario
    const allowedRoles: Role[] = route.data?.['roles'];
    if (allowedRoles && allowedRoles.length > 0) {
      const currentUser = this.authService.getCurrentUser();
      if (!currentUser?.role || !allowedRoles.includes(currentUser.role)) {
        this.router.navigate(['/auth/not-authorized']);
        return false;
      }
    }

    return true;
  }
}
