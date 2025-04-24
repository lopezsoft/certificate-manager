import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot } from '@angular/router';
import TokenService from '../utils/token.service';

@Injectable({
  providedIn: 'root'
})
export class LoginGuard  {
  constructor(private authService: TokenService, private router: Router) { }
  canActivate( route: ActivatedRouteSnapshot, state: RouterStateSnapshot)  {
    let isAuth = this.authService.isAuthenticated();
    if (isAuth) {
      this.router.navigate(['/dashboard']);
      return false;
    }else {
      return true;
    }
  }

}
