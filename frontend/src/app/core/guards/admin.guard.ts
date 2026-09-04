import { Injectable } from '@angular/core';
import { CanActivate, Router, ActivatedRouteSnapshot, RouterStateSnapshot, UrlTree } from '@angular/router';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root'
})
export class AdminGuard implements CanActivate {
  constructor(
    private router: Router,
    private authService: AuthService
  ) { }

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): Observable<boolean | UrlTree> | Promise<boolean | UrlTree> | boolean | UrlTree {
    
    const user = this.authService.getUser();
    
    // Si pas admin, rediriger vers dashboard manager
    if (!user || user.role !== 'admin') {
      return this.router.createUrlTree(['/manager/dashboard']);
    }
    
    // Vérifier que le token est toujours valide
    return this.authService.verifyToken().pipe(
      map(isValid => {
        if (!isValid) {
          return this.router.createUrlTree(['/connexion']);
        }
        
        if (user.role !== 'admin') {
          return this.router.createUrlTree(['/manager/dashboard']);
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