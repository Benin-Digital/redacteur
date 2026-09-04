import { Injectable, Inject, PLATFORM_ID } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { isPlatformBrowser } from '@angular/common';
import { environment } from '../../environments/environment';

// ─── Interfaces existantes ──────────────────────────────────────────────────

export interface Manager {
  id: number;
  name: string;
  firstname?: string;
  email: string;
  role: 'manager';
  is_active: boolean;
  last_login_at?: string;
  created_at?: string;
}

export interface Site {
  id: number;
  user_id: number;
  url: string;
  description?: string;
  contexte_json?: any;
  analyzed_at?: string;
  created_at?: string;
  user?: Manager;
}

export interface Article {
  id: number;
  site_id: number;
  user_id: number;
  titre: string;
  meta_desc?: string;
  slug?: string;
  contenu_md?: string;
  categorie?: string;
  tags?: string[];
  mot_cle_principal?: string;
  statut: 'brouillon' | 'programme' | 'publie';
  programme_le?: string;
  publie_le?: string;
  created_at?: string;
  site?: Site;
  user?: Manager;
}

export interface Log {
  id: number;
  user_id: number;
  action: string;
  details?: string;
  ip_address?: string;
  created_at?: string;
  user?: Manager;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AdminStats {
  total_users: number;
  total_managers: number;
  active_managers: number;
  inactive_managers: number;
  total_sites: number;
  total_articles: number;
  articles_publies: number;
  articles_programmes: number;
  articles_brouillon: number;
}

export interface CreateManagerPayload {
  name: string;
  firstname?: string;
  email: string;
}

export interface UpdateManagerPayload {
  name?: string;
  firstname?: string;
  email?: string;
}

// ============================================================
// NOUVELLES INTERFACES POUR LES CLÉS API
// ============================================================

export interface SystemApiKey {
  id: number;
  provider: string;
  api_key_masked: string;
  model_name: string | null;
  capabilities?: string[];
  is_active: boolean;
  priority: number;
  config: any;
  created_at: string;
  created_by?: number;
}

export interface SystemApiKeyPayload {
  provider: string;
  api_key: string;
  model_name?: string | null;
  capabilities?: string[];
  is_active?: boolean;
  priority?: number;
  config?: any;
}

export interface ActiveLLMConfig {
  provider: string;
  model_name: string;
  capabilities?: string[];
  config: any;
}

// ─── Service ─────────────────────────────────────────────────────────────────

@Injectable({
  providedIn: 'root'
})
export class AdminService {
  private apiUrl = `${environment.apiUrl}/admin`;
  private isBrowser: boolean;

  constructor(
    private http: HttpClient,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  // ============================================================
  // GESTION DES MANAGERS (existant)
  // ============================================================

  listManagers(page: number = 1): Observable<PaginatedResponse<Manager>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<Manager>>(`${this.apiUrl}/managers`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  createManager(payload: CreateManagerPayload): Observable<{ message: string; manager: Manager }> {
    const cleanPayload = {
      ...payload,
      name: payload.name.trim(),
      firstname: payload.firstname?.trim() || undefined,
      email: payload.email.trim().toLowerCase()
    };
    return this.http.post<{ message: string; manager: Manager }>(`${this.apiUrl}/managers`, cleanPayload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  getManager(id: number): Observable<Manager> {
    return this.http.get<Manager>(`${this.apiUrl}/managers/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  updateManager(id: number, payload: UpdateManagerPayload): Observable<{ message: string; manager: Manager }> {
    const cleanPayload: UpdateManagerPayload = {};
    if (payload.name !== undefined) cleanPayload.name = payload.name.trim();
    if (payload.firstname !== undefined) cleanPayload.firstname = payload.firstname.trim();
    if (payload.email !== undefined) cleanPayload.email = payload.email.trim().toLowerCase();

    return this.http.put<{ message: string; manager: Manager }>(`${this.apiUrl}/managers/${id}`, cleanPayload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  deleteManager(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/managers/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  toggleManagerStatus(id: number): Observable<{ message: string; is_active: boolean }> {
    return this.http.patch<{ message: string; is_active: boolean }>(`${this.apiUrl}/managers/${id}/toggle-status`, {}).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // SUPERVISION SITES (existant)
  // ============================================================

  listAllSites(page: number = 1): Observable<PaginatedResponse<Site>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<Site>>(`${this.apiUrl}/sites`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  deleteAnySite(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/sites/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // SUPERVISION ARTICLES (existant)
  // ============================================================

  listAllArticles(page: number = 1): Observable<PaginatedResponse<Article>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<Article>>(`${this.apiUrl}/articles`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  deleteAnyArticle(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/articles/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // LOGS (existant)
  // ============================================================

  getLogs(page: number = 1): Observable<PaginatedResponse<Log>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<Log>>(`${this.apiUrl}/logs`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  getUserLogs(userId: number, page: number = 1): Observable<PaginatedResponse<Log>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<Log>>(`${this.apiUrl}/logs/user/${userId}`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // STATISTIQUES (existant)
  // ============================================================

  getStats(): Observable<AdminStats> {
    return this.http.get<AdminStats>(`${this.apiUrl}/stats`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // NOUVELLES MÉTHODES : GESTION DES CLÉS API LLM
  // ============================================================

  /**
   * Liste toutes les clés API
   */
  listApiKeys(page: number = 1): Observable<PaginatedResponse<SystemApiKey>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<SystemApiKey>>(`${this.apiUrl}/api-keys`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Récupérer une clé API spécifique
   */
  getApiKey(id: number): Observable<SystemApiKey> {
    return this.http.get<SystemApiKey>(`${this.apiUrl}/api-keys/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Créer une nouvelle clé API
   */
  createApiKey(payload: SystemApiKeyPayload): Observable<{ message: string; api_key: SystemApiKey }> {
    return this.http.post<{ message: string; api_key: SystemApiKey }>(`${this.apiUrl}/api-keys`, payload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Mettre à jour une clé API
   */
  updateApiKey(id: number, payload: Partial<SystemApiKeyPayload>): Observable<{ message: string; api_key: SystemApiKey }> {
    return this.http.put<{ message: string; api_key: SystemApiKey }>(`${this.apiUrl}/api-keys/${id}`, payload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Supprimer une clé API
   */
deleteApiKey(id: number): Observable<{ message: string }> {
  return this.http.delete<{ message: string }>(
    `${this.apiUrl}/api-keys/${id}`
  ).pipe(
    catchError(this.handleError.bind(this))
  );
}

  /**
   * Tester une clé API
   */
  testApiKey(id: number): Observable<{ success: boolean; message: string }> {
    return this.http.post<{ success: boolean; message: string }>(`${this.apiUrl}/api-keys/${id}/test`, {}).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Obtenir la configuration LLM active
   * Retourne un tableau de tous les providers actifs ou un seul selon le type
   */
  getActiveConfig(type?: string): Observable<any> {
    let params = new HttpParams();
    if (type) {
      params = params.set('type', type);
    }
    return this.http.get<any>(`${this.apiUrl}/api-keys/active/config`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ============================================================
  // ERROR HANDLER (existant)
  // ============================================================

  private handleError(error: HttpErrorResponse): Observable<never> {
    let errorMessage = 'Une erreur est survenue';
    let errorDetails: any = null;

    if (error.error instanceof ErrorEvent) {
      errorMessage = error.error.message;
    } else {
      errorMessage = error.error?.message || `Erreur ${error.status}: ${error.statusText}`;
      errorDetails = error.error?.errors || error.error?.details;
    }

    console.error('AdminService Error:', { message: errorMessage, details: errorDetails, status: error.status });

    return throwError(() => ({
      message: errorMessage,
      details: errorDetails,
      status: error.status
    }));
  }
}