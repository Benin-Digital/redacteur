import { Injectable } from '@angular/core';
import { CanActivate, Router, ActivatedRouteSnapshot, RouterStateSnapshot, UrlTree } from '@angular/router';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root'
})
export class AuthGuard implements CanActivate {
  constructor(
    private router: Router,
    private authService: AuthService
  ) { }

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): Observable<boolean | UrlTree> | Promise<boolean | UrlTree> | boolean | UrlTree {
    
    const token = this.authService.getToken();
    
    if (!token) {
      return this.router.createUrlTree(['/connexion'], {
        queryParams: { returnUrl: state.url }
      });
    }

    return this.authService.verifyToken().pipe(
      map(isValid => {
        if (!isValid) {
          this.authService.logout();
          return this.router.createUrlTree(['/connexion'], {
            queryParams: { returnUrl: state.url }
          });
        }
        return true;
      }),
      catchError(() => {
        this.authService.logout();
        return of(this.router.createUrlTree(['/connexion']));
      })
    );
  }
}