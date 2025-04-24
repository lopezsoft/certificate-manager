import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { Injectable } from '@angular/core';
import TokenService from '../utils/token.service';

@Injectable()
export default class AuthGuard implements CanActivate {

  constructor(private authService: TokenService, private router: Router) { }

  canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot) {
    let isAuth = this.authService.isAuthenticated();
    if (!isAuth) {
      this.router.navigate(['/auth/login']);
      return false;
    }else {
      return true;
    }
  }
}
