import { Injectable, Inject, PLATFORM_ID } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { isPlatformBrowser } from '@angular/common';
import { environment } from '../../environments/environment';

// ─── Interfaces ──────────────────────────────────────────────────────────────

export interface ActiveProvider {
  id: number;
  provider: string;
  model_name: string | null;
  label: string;
  icon: string;
}

export interface ActiveProvidersResponse {
  success: boolean;
  type: 'image' | 'video';
  providers: ActiveProvider[];
}

// ─── Interfaces pour les tâches programmées ──────────────────────────────

export interface ScheduledTask {
  id: number;
  type: 'article' | 'media';
  article_id?: number;
  media_id?: number;
  platform?: string;
  caption?: string;
  scheduled_at: string;
  status: 'pending' | 'processing' | 'executed' | 'failed' | 'cancelled';
  metadata?: any;
  error_message?: string;
  processed_at?: string;
  created_at: string;
  updated_at: string;
  media?: IndependentMedia;
  article?: Article;
}

export interface ScheduledTaskResponse {
  tasks: ScheduledTask[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

export interface ScheduleMediaPayload {
  platform: 'tiktok' | 'instagram' | 'facebook' | 'twitter';
  scheduled_at: string;
  caption?: string;
}

export interface UpdateScheduledTaskPayload {
  scheduled_at?: string;
  platform?: 'tiktok' | 'instagram' | 'facebook' | 'twitter';
  caption?: string;
}

// ============================================================
// INTERFACES POUR MÉDIAS INDÉPENDANTS
// ============================================================

export interface GenerateIndependentImagePayload {
  prompt: string;
  aspect_ratio?: '16:9' | '9:16' | '1:1';
  style?: string;
  count?: number;
}

export interface GenerateIndependentVideoPayload {
  prompt: string;
  duration?: number;
  aspect_ratio?: '16:9' | '9:16';
  style?: string;
}

export interface IndependentImageResponse {
  success: boolean;
  message: string;
  images: IndependentMedia[];
}

export interface IndependentVideoResponse {
  success: boolean;
  message: string;
  video: IndependentMedia;
}

export interface IndependentMedia {
  id: number;
  url: string;
  file_name: string;
  file_size: number;
  width?: number;
  height?: number;
  duration_seconds?: number;
  provider?: string;
  model_used?: string;
  prompt_used?: string;
  type?: 'image' | 'video';
  created_at: string;
  published?: boolean;
  published_to_tiktok?: boolean;
  published_to_instagram?: boolean;
  published_at?: string | null;
  is_published?: boolean;
  is_scheduled?: boolean;
  is_failed?: boolean;
  is_pending?: boolean;
  publication_results?: {
    tiktok?: {
      post_id?: string;
      post_url?: string;
      published_at?: string;
    };
    instagram?: {
      post_id?: string;
      post_url?: string;
      published_at?: string;
    };
  };
}

export interface LibraryResponse {
  data: IndependentMedia[];
  total: number;
  current_page: number;
  per_page: number;
  last_page: number;
}

export interface LibraryFilters {
  type?: 'image' | 'video';
  search?: string;
  page?: number;
  per_page?: number;
}

export interface WordPressConfig {
  id: number;
  site_id: number;
  wp_url: string;
  wp_username: string;
  wp_app_password_masked: string;
  is_active: boolean;
  last_test_status: boolean | null;
  last_test_at: string | null;
  last_used_at: string | null;
  use_count: number;
}

export interface SuggestedTheme {
  titre: string;
  mot_cle_principal: string;
}

export interface WordPressConfigPayload {
  wp_url: string;
  wp_username: string;
  wp_app_password: string;
  test_connection?: boolean;
}

export interface WordPressConfigResponse {
  message: string;
  config: {
    id: number;
    wp_url: string;
    wp_username: string;
    wp_app_password_masked: string;
    is_active: boolean;
  };
  connection_test?: {
    success: boolean;
    message: string;
    user?: string;
    site?: string;
  };
}

export interface SiteWithConfig extends Site {
  wordpressConfig?: WordPressConfig;
}

export interface Site {
  id: number;
  user_id: number;
  url: string;
  description?: string;
  contexte_json?: any;
  wordpressConfig?: WordPressConfig;
  analyzed_at?: string;
  created_at?: string;
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
  wp_url?: string;
  wp_post_id?: number;
  created_at?: string;
  site?: Site;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AnalyzeSitePayload {
  url: string;
  description?: string;
}

export interface GenerateArticlePayload {
  site_id: number;
  sujet: string;
  mot_cle_principal: string;
  longueur_cible?: number;
}

export interface UpdateArticlePayload {
  titre?: string;
  meta_desc?: string;
  slug?: string;
  contenu_md?: string;
  categorie?: string;
  tags?: string[];
}

export interface ScheduleArticlePayload {
  scheduled_at: string;
}

export interface PublishResult {
  success: boolean;
  message: string;
  error?: string;
  url?: string;
  wp_id?: number;
  article_url?: string;
  requires_config?: boolean;
  site_id?: number;
  article?: {
    id: number;
    titre: string;
    statut: string;
    publie_le: string;
  };
}

export interface ScheduleResult {
  message: string;
  scheduled_at: string;
  scheduled_task_id: number;
  article: {
    id: number;
    titre: string;
    statut: string;
    programme_le: string;
  };
}

export interface CancelScheduleResult {
  success: boolean;
  message: string;
  article: {
    id: number;
    titre: string;
    statut: string;
  };
}

// ─── Interfaces pour l'upload ──────────────────────────────────────────────

export interface UploadMediaResponse {
  success: boolean;
  message: string;
  media: IndependentMedia;
}

// ─── Interfaces pour les médias ──────────────────────────────────────────────

export interface MediaGenerationResponse {
  success: boolean;
  message: string;
  prompt_id: number;
  images?: MediaImage[];
  video?: MediaVideo;
}

export interface MediaImage {
  id: number;
  url: string;
  file_name: string;
  width: number;
  height: number;
  is_hero: boolean | null;
}

export interface MediaVideo {
  id: number;
  url: string;
  file_name: string;
  duration: number;
}

export interface GenerateImagesPayload {
  count?: number;
  aspect_ratio?: '16:9' | '9:16' | '1:1';
  style?: string;
  custom_prompt?: string;
  provider?: 'openai' | 'google' | 'elevenlabs';
}

export interface GenerateVideoPayload {
  duration?: number;
  aspect_ratio?: '16:9' | '9:16';
  style?: string;
  custom_prompt?: string;
  provider?: 'google' | 'openai' | 'elevenlabs';
}

export interface IntegrateMediaPayload {
  media_ids: number[];
  position?: 'top' | 'bottom' | 'inline';
}

export interface ArticleMedia {
  id: number;
  type: 'image' | 'video';
  url: string;
  file_name: string;
  is_hero: boolean;
  is_integrated: boolean;
  metadata: any;
  created_at: string;
}

export interface GetMediaResponse {
  media: ArticleMedia[];
}

// ─── Service ─────────────────────────────────────────────────────────────────

@Injectable({
  providedIn: 'root'
})
export class ManagerService {
  private apiUrl = environment.apiUrl;
  private isBrowser: boolean;

  constructor(
    private http: HttpClient,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  // ── Sites ──────────────────────────────────────────────────────────────────

  analyzeSite(payload: AnalyzeSitePayload & { provider?: string; model_name?: string }): Observable<{ message: string; site: Site }> {
    const cleanPayload = {
      ...payload,
      url: payload.url.trim(),
      description: payload.description?.trim() || undefined
    };
    return this.http.post<{ message: string; site: Site }>(`${this.apiUrl}/sites/analyze`, cleanPayload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  listSites(page: number = 1): Observable<PaginatedResponse<Site>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<Site>>(`${this.apiUrl}/sites`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  getSite(id: number): Observable<Site> {
    return this.http.get<Site>(`${this.apiUrl}/sites/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  deleteSite(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/sites/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ── Médias Indépendants (Génération & Bibliothèque) ──────────────────────

  /**
   * Générer des images indépendamment (sans article)
   */
  generateIndependentImages(payload: GenerateIndependentImagePayload): Observable<IndependentImageResponse> {
    const cleanPayload = {
      prompt: payload.prompt.trim(),
      aspect_ratio: payload.aspect_ratio ?? '16:9',
      style: payload.style ?? 'moderne, professionnel',
      count: payload.count ?? 1
    };
    return this.http.post<IndependentImageResponse>(`${this.apiUrl}/media/generate-image`, cleanPayload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Générer une vidéo indépendamment (sans article)
   */
  generateIndependentVideo(payload: GenerateIndependentVideoPayload): Observable<IndependentVideoResponse> {
    const cleanPayload = {
      prompt: payload.prompt.trim(),
      duration: payload.duration ?? 8,
      aspect_ratio: payload.aspect_ratio ?? '16:9',
      style: payload.style ?? 'cinematographique, professionnel'
    };
    return this.http.post<IndependentVideoResponse>(`${this.apiUrl}/media/generate-video`, cleanPayload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Récupérer la bibliothèque de médias de l'utilisateur
   */
  getMediaLibrary(filters: LibraryFilters = {}): Observable<LibraryResponse> {
    let params = new HttpParams();

    if (filters.type) {
      params = params.set('type', filters.type);
    }
    if (filters.search) {
      params = params.set('search', filters.search.trim());
    }
    if (filters.page) {
      params = params.set('page', filters.page.toString());
    }
    if (filters.per_page) {
      params = params.set('per_page', filters.per_page.toString());
    }

    return this.http.get<LibraryResponse>(`${this.apiUrl}/media/library`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
 * Récupérer la liste des modèles LLM disponibles pour la génération de texte
 */
  getAvailableTextModels(): Observable<{ models: { provider: string; model_name: string; is_active: boolean }[] }> {
    return this.http.get<{ models: { provider: string; model_name: string; is_active: boolean }[] }>(
      `${this.apiUrl}/llm/models/text`
    ).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Récupérer un média spécifique
   */
  getMedia(id: number): Observable<IndependentMedia> {
    return this.http.get<IndependentMedia>(`${this.apiUrl}/media/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
 * Récupérer la liste des providers actifs pour un type de média
 */
  getActiveProviders(type: 'image' | 'video'): Observable<ActiveProvidersResponse> {
    const params = new HttpParams().set('type', type);
    return this.http.get<ActiveProvidersResponse>(`${this.apiUrl}/media/active-providers`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Supprimer un média
   */
  deleteIndependentMedia(id: number): Observable<{ success: boolean; message: string }> {
    return this.http.delete<{ success: boolean; message: string }>(`${this.apiUrl}/media/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Télécharger un média
   */
  downloadIndependentMedia(id: number): Observable<{ download_url: string; file_name: string }> {
    return this.http.get<{ download_url: string; file_name: string }>(`${this.apiUrl}/media/${id}/download`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Uploader un fichier média (image ou vidéo)
   */
  uploadMedia(file: File): Observable<UploadMediaResponse> {
    const formData = new FormData();
    formData.append('file', file);

    return this.http.post<UploadMediaResponse>(`${this.apiUrl}/media/upload`, formData).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Uploader plusieurs fichiers
   */
  uploadMultipleMedia(files: File[]): Observable<any> {
    const formData = new FormData();
    files.forEach(file => {
      formData.append('files[]', file);
    });

    return this.http.post(`${this.apiUrl}/media/upload-multiple`, formData).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Publier un média sur un réseau social (immédiat)
   */
  publishMediaToSocial(mediaId: number, platform: string, caption?: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/media/${mediaId}/publish`, {
      platform,
      caption: caption || ''
    }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Programmer la publication d'un média
   */
  scheduleMediaPublication(mediaId: number, payload: ScheduleMediaPayload): Observable<any> {
    return this.http.post(`${this.apiUrl}/media/${mediaId}/schedule`, payload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Lister les tâches programmées
   */
  getScheduledTasks(page: number = 1, perPage: number = 20): Observable<ScheduledTaskResponse> {
    const params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());

    return this.http.get<ScheduledTaskResponse>(`${this.apiUrl}/media/scheduled-tasks`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Modifier une tâche programmée
   */
  updateScheduledTask(taskId: number, payload: UpdateScheduledTaskPayload): Observable<any> {
    return this.http.put(`${this.apiUrl}/media/scheduled-tasks/${taskId}`, payload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Supprimer/annuler une tâche programmée
   */
  deleteScheduledTask(taskId: number): Observable<{ success: boolean; message: string }> {
    return this.http.delete<{ success: boolean; message: string }>(`${this.apiUrl}/media/scheduled-tasks/${taskId}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ── Articles ───────────────────────────────────────────────────────────────

  generateArticle(payload: GenerateArticlePayload & { provider?: string; model_name?: string }): Observable<{ message: string; article: Article }> {
    const cleanPayload = {
      ...payload,
      sujet: payload.sujet.trim(),
      mot_cle_principal: payload.mot_cle_principal.trim(),
      longueur_cible: payload.longueur_cible ?? 800
    };
    return this.http.post<{ message: string; article: Article }>(`${this.apiUrl}/articles/generate`, cleanPayload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  listArticles(page: number = 1): Observable<PaginatedResponse<Article>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<Article>>(`${this.apiUrl}/articles`, { params }).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  getArticle(id: number): Observable<Article> {
    return this.http.get<Article>(`${this.apiUrl}/articles/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  updateArticle(id: number, payload: UpdateArticlePayload): Observable<{ message: string; article: Article }> {
    const cleanPayload: UpdateArticlePayload = {};
    if (payload.titre !== undefined) cleanPayload.titre = payload.titre.trim();
    if (payload.meta_desc !== undefined) cleanPayload.meta_desc = payload.meta_desc.trim();
    if (payload.contenu_md !== undefined) cleanPayload.contenu_md = payload.contenu_md;
    if (payload.categorie !== undefined) cleanPayload.categorie = payload.categorie.trim();
    if (payload.tags !== undefined) cleanPayload.tags = payload.tags;

    return this.http.put<{ message: string; article: Article }>(`${this.apiUrl}/articles/${id}`, cleanPayload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  publishArticle(id: number): Observable<PublishResult> {
    return this.http.post<PublishResult>(`${this.apiUrl}/articles/${id}/publish`, {}).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  scheduleArticle(id: number, payload: ScheduleArticlePayload): Observable<ScheduleResult> {
    return this.http.post<ScheduleResult>(`${this.apiUrl}/articles/${id}/schedule`, payload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  cancelSchedule(id: number): Observable<CancelScheduleResult> {
    return this.http.delete<CancelScheduleResult>(`${this.apiUrl}/articles/${id}/schedule`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  scheduledArticles(): Observable<Article[]> {
    return this.http.get<Article[]>(`${this.apiUrl}/articles/scheduled`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  updateSchedule(id: number, payload: ScheduleArticlePayload): Observable<ScheduleResult> {
    return this.http.put<ScheduleResult>(`${this.apiUrl}/articles/${id}/schedule`, payload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ── Delete Article ─────────────────────────────────────────────────────────

  /**
   * Supprimer un article
   */
  deleteArticle(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/articles/${id}`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  getSiteThemes(siteId: number): Observable<{ themes: SuggestedTheme[]; has_themes: boolean }> {
    return this.http.get<{ themes: SuggestedTheme[]; has_themes: boolean }>(`${this.apiUrl}/sites/${siteId}/themes`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ── Médias (Images & Vidéos) ─────────────────────────────────────────────────

  /**
   * Générer des images pour un article
   */
  generateImages(articleId: number, payload: GenerateImagesPayload): Observable<MediaGenerationResponse> {
    const cleanPayload = {
      count: payload.count ?? 3,
      aspect_ratio: payload.aspect_ratio ?? '16:9',
      style: payload.style ?? 'moderne, professionnel',
      custom_prompt: payload.custom_prompt?.trim() || undefined,
      provider: payload.provider || undefined
    };
    return this.http.post<MediaGenerationResponse>(
      `${this.apiUrl}/articles/${articleId}/media/images/generate`,
      cleanPayload
    ).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Générer une vidéo pour un article
   */
  generateVideo(articleId: number, payload: GenerateVideoPayload): Observable<MediaGenerationResponse> {
    const cleanPayload = {
      duration: payload.duration ?? 8,
      aspect_ratio: payload.aspect_ratio ?? '16:9',
      style: payload.style ?? 'cinematographique, professionnel',
      custom_prompt: payload.custom_prompt?.trim() || undefined,
      provider: payload.provider || undefined
    };
    return this.http.post<MediaGenerationResponse>(
      `${this.apiUrl}/articles/${articleId}/media/video/generate`,
      cleanPayload
    ).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Récupérer tous les médias d'un article
   */
  getArticleMedia(articleId: number): Observable<GetMediaResponse> {
    return this.http.get<GetMediaResponse>(`${this.apiUrl}/articles/${articleId}/media`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Définir une image comme image principale
   */
  setHeroImage(articleId: number, mediaId: number): Observable<{ success: boolean; message: string }> {
    return this.http.post<{ success: boolean; message: string }>(
      `${this.apiUrl}/articles/${articleId}/media/${mediaId}/set-hero`,
      {}
    ).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Intégrer des médias dans l'article
   */
  integrateMedia(articleId: number, payload: IntegrateMediaPayload): Observable<{ success: boolean; message: string; article: Article }> {
    return this.http.post<{ success: boolean; message: string; article: Article }>(
      `${this.apiUrl}/articles/${articleId}/media/integrate`,
      payload
    ).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Supprimer un média
   */
  deleteMedia(articleId: number, mediaId: number): Observable<{ success: boolean; message: string }> {
    return this.http.delete<{ success: boolean; message: string }>(
      `${this.apiUrl}/articles/${articleId}/media/${mediaId}`
    ).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ── WordPress Configurations ────────────────────────────────────────────────

  /**
   * Récupérer tous les sites avec leur configuration WordPress
   */
  listSitesWithWpConfig(): Observable<PaginatedResponse<SiteWithConfig>> {
    return this.http.get<PaginatedResponse<SiteWithConfig>>(`${this.apiUrl}/sites/wp-configs`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Récupérer la configuration WordPress d'un site spécifique
   */
  getSiteWpConfig(siteId: number): Observable<WordPressConfig> {
    return this.http.get<WordPressConfig>(`${this.apiUrl}/sites/${siteId}/wp-config`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Créer ou mettre à jour la configuration WordPress d'un site
   */
  saveSiteWpConfig(siteId: number, payload: WordPressConfigPayload): Observable<WordPressConfigResponse> {
    return this.http.post<WordPressConfigResponse>(`${this.apiUrl}/sites/${siteId}/wp-config`, payload).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Supprimer la configuration WordPress d'un site
   */
  deleteSiteWpConfig(siteId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/sites/${siteId}/wp-config`).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  /**
   * Tester la connexion WordPress d'un site
   */
  testWpConnection(siteId: number): Observable<{ success: boolean; message: string; user?: string; site?: string }> {
    return this.http.post<{ success: boolean; message: string; user?: string; site?: string }>(
      `${this.apiUrl}/sites/${siteId}/wp-config/test`,
      {}
    ).pipe(
      catchError(this.handleError.bind(this))
    );
  }

  // ── Error handler ──────────────────────────────────────────────────────────

  private handleError(error: HttpErrorResponse): Observable<never> {
    let errorMessage = 'Une erreur est survenue';
    let errorDetails: any = null;
    let requiresConfig = false;
    let siteId = null;

    if (error.error instanceof ErrorEvent) {
      errorMessage = error.error.message;
    } else {
      // Extraire les propriétés importantes de l'erreur
      errorMessage = error.error?.message || error.error?.error || `Erreur ${error.status}: ${error.statusText}`;
      errorDetails = error.error?.errors || error.error?.details;
      requiresConfig = error.error?.requires_config === true;
      siteId = error.error?.site_id;
    }

    console.error('ManagerService Error:', {
      message: errorMessage,
      details: errorDetails,
      status: error.status,
      requires_config: requiresConfig,
      site_id: siteId
    });

    // Retourner un objet avec toutes les propriétés
    return throwError(() => ({
      message: errorMessage,
      details: errorDetails,
      status: error.status,
      requires_config: requiresConfig,
      site_id: siteId
    }));
  }
}