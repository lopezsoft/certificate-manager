import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree } from '@angular/router';
import { Observable } from 'rxjs';
import TokenService from '../utils/token.service';


@Injectable({
  providedIn: 'root'
})
export default class AuthGuard  {

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
