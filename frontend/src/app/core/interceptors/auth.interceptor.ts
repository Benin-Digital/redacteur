// src/app/core/interceptors/auth.interceptor.ts
import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { AuthService } from '../services/auth.service';
import { Router } from '@angular/router';

@Injectable()
export class AuthInterceptor implements HttpInterceptor {
  // URLs où un 401 ne signifie PAS "session expirée",
  // mais une erreur métier (mauvais code 2FA, etc.)
  private readonly safeUrls = [
    '/api/login',
    '/api/profile',
    '/api/password/change',
    '/api/login/verify-2fa',
    '/api/login/resend-code',
  ];

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    const token = this.authService.getToken();

    // Ajouter le token si présent et si ce n'est pas une requête de login ou 2FA
    if (token) {
      req = req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`
        }
      });
    }

    return next.handle(req).pipe(
      catchError((error: HttpErrorResponse) => {
        if (error.status === 401) {
          const isSafeUrl = this.safeUrls.some(safeUrl => req.url.includes(safeUrl));

          if (!isSafeUrl) {
            // Vrai token expiré/invalide → on nettoie localement,
            // sans rappeler /api/logout (le token n'est déjà plus valable)
            this.authService.forceLogout();
            this.router.navigate(['/connexion'], {
              queryParams: { session_expired: true }
            });
          }
        }
        return throwError(() => error);
      })
    );
  }
}