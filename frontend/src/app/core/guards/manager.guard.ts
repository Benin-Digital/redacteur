import { Injectable } from '@angular/core';
import { CanActivate, Router, ActivatedRouteSnapshot, RouterStateSnapshot, UrlTree } from '@angular/router';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root'
})
export class ManagerGuard implements CanActivate {
  constructor(
    private router: Router,
    private authService: AuthService
  ) { }

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): Observable<boolean | UrlTree> | Promise<boolean | UrlTree> | boolean | UrlTree {
    
    const user = this.authService.getUser();
    
    // Si pas manager (et pas admin), rediriger vers connexion ou admin
    if (!user || (user.role !== 'manager' && user.role !== 'admin')) {
      // Si c'est un admin, rediriger vers son dashboard
      if (user?.role === 'admin') {
        return this.router.createUrlTree(['/admin/dashboard']);
      }
      return this.router.createUrlTree(['/connexion']);
    }
    
    // Vérifier que le token est toujours valide
    return this.authService.verifyToken().pipe(
      map(isValid => {
        if (!isValid) {
          this.authService.logout();
          return this.router.createUrlTree(['/connexion']);
        }
        
        const currentUser = this.authService.getUser();
        if (!currentUser || (currentUser.role !== 'manager' && currentUser.role !== 'admin')) {
          return this.router.createUrlTree(['/connexion']);
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