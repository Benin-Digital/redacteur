// src/app/core/services/auth.service.ts
import { Injectable, Inject, PLATFORM_ID } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError, BehaviorSubject } from 'rxjs';
import { catchError, map, tap } from 'rxjs/operators';
import { isPlatformBrowser } from '@angular/common';
import { environment } from '../../environments/environment';

// ============================================================
// INTERFACES
// ============================================================

export interface User {
  id: number;
  name: string;
  firstname: string;
  email: string;
  role: 'admin' | 'manager';
  is_active: boolean;
  is_approved?: boolean;
  approved_at?: string;
  last_login_at?: string;
  password_changed_at?: string | null;
  created_at?: string;
}

export interface LoginResponse {
  message: string;
  access_token: string;
  token_type: string;
  expires_in: number;
  is_first_login: boolean;
  user: User;
  requires_two_factor?: boolean;
  user_id?: number;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface TwoFactorRequest {
  user_id: number;
  code: string;
}

export interface TwoFactorResponse {
  message: string;
  requires_two_factor?: boolean;
  user_id?: number;
  access_token?: string;
  token_type?: string;
  expires_in?: number;
  user?: User;
}

export interface ResendCodeResponse {
  message: string;
  expires_in?: number;
}

export interface RegisterRequest {
  name: string;
  firstname: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface RegisterResponse {
  success: boolean;
  message: string;
  data: {
    user: {
      id: number;
      name: string;
      firstname: string;
      email: string;
      status: 'pending_approval' | 'approved' | 'rejected';
    };
  };
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface ChangePasswordRequest {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export interface UpdateProfileRequest {
  name?: string;
  firstname?: string;
  email?: string;
  current_password?: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = environment.apiUrl;
  private tokenKey = 'token';
  private userKey = 'user';
  private tokenExpiryKey = 'token_expiry';
  
  // Clés 2FA mises à jour
  private twoFactorUserIdKey = '2fa_user_id';
  private twoFactorExpiresKey = '2fa_expires_in';
  private twoFactorEmailKey = '2fa_email';  // Nouvelle clé pour l'email

  private currentUserSubject = new BehaviorSubject<User | null>(null);
  public currentUser$ = this.currentUserSubject.asObservable();
  private isBrowser: boolean;

  constructor(
    private http: HttpClient,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);

    if (this.isBrowser) {
      const user = this.getUser();
      this.currentUserSubject.next(user);
    }
  }

  // ============================================================
  // GESTION DE SESSION
  // ============================================================

  private setSession(response: LoginResponse): void {
    if (!this.isBrowser) return;

    localStorage.setItem(this.tokenKey, response.access_token);
    localStorage.setItem(this.userKey, JSON.stringify(response.user));
    const expiry = Date.now() + (response.expires_in * 1000);
    localStorage.setItem(this.tokenExpiryKey, expiry.toString());
    this.currentUserSubject.next(response.user);
  }

  private clearSession(): void {
    if (!this.isBrowser) return;

    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.userKey);
    localStorage.removeItem(this.tokenExpiryKey);
    // Nettoyer aussi les données 2FA
    this.clearTwoFactorData();
    this.currentUserSubject.next(null);
  }

  // ============================================================
  // MÉTHODES 2FA
  // ============================================================

  /**
   * Stocker les données 2FA (userId, expiration, email)
   */
  setTwoFactorData(userId: number, expiresIn: number = 600, email?: string): void {
    if (!this.isBrowser) return;
    localStorage.setItem(this.twoFactorUserIdKey, userId.toString());
    localStorage.setItem(this.twoFactorExpiresKey, expiresIn.toString());
    if (email) {
      localStorage.setItem(this.twoFactorEmailKey, email);
    }
  }

  /**
   * Récupérer l'ID utilisateur pour la 2FA
   */
  getTwoFactorUserId(): number | null {
    if (!this.isBrowser) return null;
    const id = localStorage.getItem(this.twoFactorUserIdKey);
    return id ? parseInt(id) : null;
  }

  /**
   * Récupérer le temps d'expiration de la 2FA
   */
  getTwoFactorExpiresIn(): number | null {
    if (!this.isBrowser) return null;
    const expires = localStorage.getItem(this.twoFactorExpiresKey);
    return expires ? parseInt(expires) : null;
  }

  /**
   * Récupérer l'email pour la 2FA
   */
  getTwoFactorEmail(): string | null {
    if (!this.isBrowser) return null;
    return localStorage.getItem(this.twoFactorEmailKey);
  }

  /**
   * Nettoyer toutes les données 2FA
   */
  clearTwoFactorData(): void {
    if (!this.isBrowser) return;
    localStorage.removeItem(this.twoFactorUserIdKey);
    localStorage.removeItem(this.twoFactorExpiresKey);
    localStorage.removeItem(this.twoFactorEmailKey);
  }

  // ============================================================
  // AUTHENTIFICATION
  // ============================================================

  /**
   * Connexion de l'utilisateur
   * POST /api/login
   */
  login(email: string, password: string): Observable<LoginResponse> {
    const cleanEmail = email.trim().toLowerCase();

    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, {
      email: cleanEmail,
      password
    }).pipe(
      tap((response: LoginResponse) => {
        // Si 2FA requise, stocker les données (userId, expires, email)
        if (response.requires_two_factor && response.user_id) {
          this.setTwoFactorData(
            response.user_id, 
            response.expires_in || 600,
            response.user?.email || cleanEmail
          );
          return;
        }
        // Sinon, connexion normale
        if (response.access_token && this.isBrowser) {
          this.setSession(response);
        }
      }),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Vérifier le code 2FA pour l'administrateur
   * POST /api/login/verify-2fa
   */
  verifyTwoFactor(data: TwoFactorRequest): Observable<TwoFactorResponse> {
    return this.http.post<TwoFactorResponse>(`${this.apiUrl}/login/verify-2fa`, {
      user_id: data.user_id,
      code: data.code
    }).pipe(
      tap((response: TwoFactorResponse) => {
        if (response.access_token && response.user && this.isBrowser) {
          const loginResponse: LoginResponse = {
            message: response.message || 'Connexion réussie',
            access_token: response.access_token,
            token_type: response.token_type || 'Bearer',
            expires_in: response.expires_in || 86400,
            is_first_login: false,
            user: response.user
          };
          this.setSession(loginResponse);
          // Nettoyer les données 2FA après connexion réussie
          this.clearTwoFactorData();
        }
      }),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Renvoyer un nouveau code de vérification 2FA
   * POST /api/login/resend-code
   */
  resendVerificationCode(userId: number): Observable<ResendCodeResponse> {
    return this.http.post<ResendCodeResponse>(`${this.apiUrl}/login/resend-code`, { 
      user_id: userId 
    }).pipe(
      tap((response: ResendCodeResponse) => {
        if (response.expires_in && this.isBrowser) {
          localStorage.setItem(this.twoFactorExpiresKey, response.expires_in.toString());
        }
      }),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Vérifier la validité du token
   * GET /api/me
   */
  verifyToken(): Observable<boolean> {
    const token = this.getToken();
    if (!token) {
      return throwError(() => new Error('Token manquant'));
    }

    return this.http.get(`${this.apiUrl}/me`).pipe(
      map(() => true),
      catchError((error) => {
        if (this.isBrowser) {
          this.clearSession();
          this.clearTwoFactorData();
        }
        return throwError(() => error);
      })
    );
  }

  /**
   * Vérifier si l'utilisateur a besoin d'une vérification 2FA
   */
  handleTwoFactorResponse(response: any): { requiresTwoFactor: boolean; userId?: number; expiresIn?: number } {
    if (response && response.requires_two_factor) {
      return {
        requiresTwoFactor: true,
        userId: response.user_id,
        expiresIn: response.expires_in || 600
      };
    }
    return { requiresTwoFactor: false };
  }

  // ============================================================
  // INSCRIPTION
  // ============================================================

  /**
   * Inscription d'un nouveau documentaliste (en attente d'approbation)
   * POST /api/register
   */
  register(data: RegisterRequest): Observable<RegisterResponse> {
    const cleanData = {
      ...data,
      name: data.name.trim(),
      firstname: data.firstname.trim(),
      email: data.email.trim().toLowerCase()
    };
    return this.http.post<RegisterResponse>(`${this.apiUrl}/register`, cleanData).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // DÉCONNEXION
  // ============================================================

  /**
   * Déconnexion de l'utilisateur
   * POST /api/logout
   */
  logout(): void {
    if (!this.isBrowser) return;

    const token = this.getToken();
    if (token) {
      this.http.post(`${this.apiUrl}/logout`, {}).subscribe({
        next: () => {
          this.clearSession();
          this.clearTwoFactorData();
        },
        error: () => {
          this.clearSession();
          this.clearTwoFactorData();
        }
      });
    } else {
      this.clearSession();
      this.clearTwoFactorData();
    }
  }

  /**
   * Nettoyage local de session, sans appel API
   */
  forceLogout(): void {
    this.clearSession();
    this.clearTwoFactorData();
  }

  // ============================================================
  // UTILISATEUR
  // ============================================================

  /**
   * Récupérer l'utilisateur connecté
   * GET /api/me
   */
  getMe(): Observable<User> {
    return this.http.get<User>(`${this.apiUrl}/me`).pipe(
      tap((user: User) => {
        if (this.isBrowser) {
          localStorage.setItem(this.userKey, JSON.stringify(user));
          this.currentUserSubject.next(user);
        }
      }),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Mettre à jour le profil utilisateur
   * PUT /api/profile
   */
  updateProfile(data: UpdateProfileRequest): Observable<any> {
    return this.http.put(`${this.apiUrl}/profile`, data).pipe(
      tap((response: any) => {
        if (response.user && this.isBrowser) {
          const user = response.user as User;
          localStorage.setItem(this.userKey, JSON.stringify(user));
          this.currentUserSubject.next(user);
        }
      }),
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Changer le mot de passe
   * PUT /api/password/change
   */
  changePassword(data: ChangePasswordRequest): Observable<any> {
    return this.http.put(`${this.apiUrl}/password/change`, data).pipe(
      tap(() => {
        if (this.isBrowser) {
          const currentUser = this.getUser();
          if (currentUser) {
            currentUser.password_changed_at = new Date().toISOString();
            localStorage.setItem(this.userKey, JSON.stringify(currentUser));
            this.currentUserSubject.next(currentUser);
          }
        }
      }),
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // MOT DE PASSE OUBLIÉ
  // ============================================================

  /**
   * Demander un lien de réinitialisation de mot de passe
   * POST /api/password/forgot
   */
  forgotPassword(data: ForgotPasswordRequest): Observable<any> {
    const cleanEmail = data.email.trim().toLowerCase();
    return this.http.post(`${this.apiUrl}/password/forgot`, { email: cleanEmail }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Réinitialiser le mot de passe avec le token
   * POST /api/password/reset
   */
  resetPassword(data: ResetPasswordRequest): Observable<any> {
    const cleanData = {
      ...data,
      email: data.email.trim().toLowerCase()
    };
    return this.http.post(`${this.apiUrl}/password/reset`, cleanData).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // MÉTHODES UTILITAIRES
  // ============================================================

  getToken(): string | null {
    if (!this.isBrowser) return null;
    return localStorage.getItem(this.tokenKey);
  }

  getUser(): User | null {
    if (!this.isBrowser) return null;

    const user = localStorage.getItem(this.userKey);
    if (!user) return null;

    try {
      return JSON.parse(user);
    } catch {
      return null;
    }
  }

  getCurrentUser(): User | null {
    return this.currentUserSubject.getValue();
  }

  isAuthenticated(): boolean {
    if (!this.isBrowser) return false;

    const token = this.getToken();
    if (!token) return false;

    const expiry = localStorage.getItem(this.tokenExpiryKey);
    if (expiry && Date.now() > parseInt(expiry)) {
      this.clearSession();
      return false;
    }

    return true;
  }

  isAdmin(): boolean {
    const user = this.getUser();
    return user?.role === 'admin';
  }

  isManager(): boolean {
    const user = this.getUser();
    return user?.role === 'manager' || user?.role === 'admin';
  }

  isFirstLogin(): boolean {
    const user = this.getUser();
    return user?.password_changed_at === null || user?.password_changed_at === undefined;
  }

  getFullName(): string {
    const user = this.getUser();
    if (!user) return '';
    return `${user.firstname} ${user.name}`;
  }

  getInitials(): string {
    const user = this.getUser();
    if (!user) return '';
    return `${user.firstname.charAt(0)}${user.name.charAt(0)}`.toUpperCase();
  }

  // ============================================================
  // GESTION DES ERREURS
  // ============================================================

  private handleError(error: HttpErrorResponse): Observable<never> {
    let errorMessage = 'Une erreur est survenue';
    let errorDetails: any = null;

    if (error.error instanceof ErrorEvent) {
      errorMessage = error.error.message;
    } else {
      errorMessage = error.error?.message || `Erreur ${error.status}: ${error.statusText}`;
      errorDetails = error.error?.errors || error.error?.details;

      if (error.status === 403 && error.error?.account_disabled) {
        errorMessage = 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.';
      }

      if (error.status === 403 && error.error?.status === 'pending_approval') {
        errorMessage = 'Votre compte est en attente d\'approbation par l\'administrateur.';
      }

      if (error.status === 401 && this.isBrowser) {
        const url = error.url || '';
        const safeUrls = [
          '/login',
          '/profile',
          '/password/change',
          '/login/verify-2fa',
          '/login/resend-code'
        ];

        const isSafeUrl = safeUrls.some(safeUrl => url.includes(safeUrl));

        if (!isSafeUrl) {
          this.clearSession();
          this.clearTwoFactorData();
        }
      }
    }

    console.error('AuthService Error:', { message: errorMessage, details: errorDetails, status: error.status });

    return throwError(() => ({
      message: errorMessage,
      details: errorDetails,
      status: error.status
    }));
  }
}