// media/library/library.component.ts

import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ManagerService, IndependentMedia, LibraryResponse, ScheduledTask, ScheduleMediaPayload } from '../../../../core/services/manager.service';
import { AuthService, User } from '../../../../core/services/auth.service';
import { SidebarComponent } from '../../../../shared/components/sidebar/sidebar/sidebar.component';
import { environment } from '../../../../environments/environment';
import { HttpClient } from '@angular/common/http';

@Component({
  selector: 'app-library',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, SidebarComponent],
  templateUrl: './library.component.html',
  styleUrl: './library.component.css'
})
export class LibraryComponent implements OnInit {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = false;
  isUploading = false;
  errorMessage: string | null = null;
  successMessage: string | null = null;

  // Données
  medias: IndependentMedia[] = [];
  total = 0;
  currentPage = 1;
  perPage = 20;
  totalPages = 0;

  // Compteurs pour les badges
  publishedCount = 0;
  scheduledCount = 0;
  failedCount = 0;
  pendingCount = 0;
  showEditScheduleModal = false;
  editScheduleDateTime: string = '';
  isUpdatingSchedule = false;
  editScheduleError: string | null = null;
  selectedTaskId: number | null = null;

  compatibilityMessage: string | null = null;
  isCompatible: boolean = true;

  showCancelConfirmationModal = false;
  isCancelling = false;

  // Compteurs par type
  imageCount = 0;
  videoCount = 0;

  // Filtres
  filters = {
    type: '' as '' | 'image' | 'video',
    search: '',
    status: 'all' as 'all' | 'published' | 'scheduled' | 'pending' | 'failed'
  };

  // Média sélectionné pour le modal
  selectedMedia: IndependentMedia | null = null;
  showMediaModal = false;

  // Média sélectionné pour la suppression
  mediaToDelete: IndependentMedia | null = null;
  showDeleteModal = false;
  isDeleting = false;

  // Upload
  showUploadModal = false;
  selectedFiles: File[] = [];
  uploadProgress = 0;
  isUploadingFile = false;

  // Publication sociale
  showShareModal = false;
  mediaToShare: IndependentMedia | null = null;
  sharePlatform: string = '';
  shareCaption: string = '';
  isPublishing = false;
  showScheduleShare = false;
  scheduleDateTime: string = '';

  // Données fictives pour la démo
  useMockData = false;

  constructor(
    private managerService: ManagerService,
    private authService: AuthService,
    private http: HttpClient

  ) { }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    this.loadMedias();
  }

  // ============================================================
  // CHARGEMENT DES MÉDIAS
  // ============================================================

  loadMedias(): void {
    this.isLoading = true;
    this.errorMessage = null;

    const params: any = {
      page: this.currentPage,
      per_page: this.perPage,
      type: this.filters.type || undefined,
      search: this.filters.search || undefined
    };

    // Filtre de statut
    if (this.filters.status === 'published') {
      params.published = true;
    } else if (this.filters.status === 'pending') {
      params.published = false;
    }

    this.managerService.getMediaLibrary(params).subscribe({
      next: (response: any) => {
        this.medias = response.data.map((item: any) => ({
          id: item.id,
          url: item.file_url || item.url,
          file_name: item.file_name,
          file_size: item.file_size,
          width: item.width,
          height: item.height,
          duration_seconds: item.duration_seconds,
          provider: item.provider,
          model_used: item.model_used,
          prompt_used: item.prompt_used,
          type: item.type || 'image',
          created_at: item.created_at,
          published_to_tiktok: item.published_to_tiktok,
          published_to_instagram: item.published_to_instagram,
          published_at: item.published_at,
          is_published: item.is_published || false,
          is_scheduled: item.is_scheduled || false,
          is_failed: item.is_failed || false,
          is_pending: item.is_pending || false
        }));

        // Utiliser les stats de la réponse
        if (response.stats) {
          this.imageCount = response.stats.images || 0;
          this.videoCount = response.stats.videos || 0;
          this.publishedCount = response.stats.published || 0;
          this.scheduledCount = response.stats.scheduled || 0;
          this.failedCount = response.stats.failed || 0;
          this.pendingCount = response.stats.pending || 0;
          this.total = response.stats.total || 0;
        } else {
          // Fallback: calculer manuellement
          this.imageCount = this.medias.filter(m => m.type === 'image').length;
          this.videoCount = this.medias.filter(m => m.type === 'video').length;
          this.publishedCount = this.medias.filter(m => m.is_published).length;
          this.scheduledCount = this.medias.filter(m => m.is_scheduled).length;
          this.failedCount = this.medias.filter(m => m.is_failed).length;
          this.pendingCount = this.medias.filter(m => m.is_pending).length;
          this.total = this.medias.length;
        }

        // Filtrer selon le statut sélectionné
        this.applyStatusFilter();

        this.totalPages = Math.ceil(this.total / this.perPage);
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Erreur chargement des médias:', error);
        this.errorMessage = error.message || 'Erreur lors du chargement des médias';
        setTimeout(() => this.errorMessage = null, 5000);
        this.isLoading = false;
        this.medias = [];
        this.total = 0;
        this.totalPages = 0;
        this.publishedCount = 0;
        this.scheduledCount = 0;
        this.failedCount = 0;
        this.pendingCount = 0;
        this.imageCount = 0;
        this.videoCount = 0;
      }
    });
  }

  // === MÉTHODE APPELÉE QUAND LA PLATEFORME CHANGE ===
  onPlatformChange(platform: string): void {
    this.sharePlatform = platform;

    // Réinitialiser les messages
    this.compatibilityMessage = null;
    this.isCompatible = true;

    // Vérifier la compatibilité (pour Instagram uniquement)
    if (platform === 'instagram') {
      this.checkCompatibilityAndShowAlert();
    }
  }

  // === VÉRIFIER LA COMPATIBILITÉ INSTAGRAM ===
  checkCompatibilityAndShowAlert(): void {
    if (!this.mediaToShare || this.sharePlatform !== 'instagram') {
      this.compatibilityMessage = null;
      this.isCompatible = true;
      return;
    }

    const result = this.checkInstagramCompatibility(this.mediaToShare);
    if (!result.compatible) {
      this.isCompatible = false;
      this.compatibilityMessage = result.message || 'Format non compatible avec Instagram.';
    } else {
      this.isCompatible = true;
      this.compatibilityMessage = null;
    }
  }

  // === MÉTHODE POUR OBTENIR LE MESSAGE D'ALERTE AVEC DÉTAILS ===
  getCompatibilityDetails(media: IndependentMedia | null, platform: string): string {
    if (!media) return '';

    if (platform === 'instagram' && media.type === 'image' && media.width && media.height) {
      const ratio = media.width / media.height;
      const ratioStr = ratio.toFixed(2);
      const dimensions = `${media.width}x${media.height}`;

      // Vérifier les ratios supportés
      const supportedRatios = [
        { name: '1:1 (carré)', min: 0.98, max: 1.02 },
        { name: '4:5 (portrait)', min: 0.78, max: 0.82 },
        { name: '1.91:1 (paysage)', min: 1.88, max: 1.94 }
      ];

      let ratioName = 'non supporté';
      for (const r of supportedRatios) {
        if (ratio >= r.min && ratio <= r.max) {
          ratioName = r.name;
          break;
        }
      }

      return `Dimensions: ${dimensions} | Ratio: ${ratioStr} (${ratioName})`;
    }

    if (platform === 'instagram' && media.type === 'video' && media.width && media.height) {
      const ratio = media.width / media.height;
      const ratioStr = ratio.toFixed(2);
      const dimensions = `${media.width}x${media.height}`;
      const duration = media.duration_seconds ? ` | Durée: ${media.duration_seconds}s` : '';

      return `Dimensions: ${dimensions} | Ratio: ${ratioStr}${duration}`;
    }

    return '';
  }

  // Appliquer le filtre de statut sur les médias chargés
  private applyStatusFilter(): void {
    let filtered = [...this.medias];

    switch (this.filters.status) {
      case 'published':
        filtered = filtered.filter(m => m.is_published);
        break;
      case 'scheduled':
        filtered = filtered.filter(m => m.is_scheduled);
        break;
      case 'failed':
        filtered = filtered.filter(m => m.is_failed);
        break;
      case 'pending':
        filtered = filtered.filter(m => m.is_pending);
        break;
      default:
        break;
    }

    this.medias = filtered;
    this.total = filtered.length;
  }

  // ============================================================
  // MÉTHODES DE FILTRAGE
  // ============================================================

  // Filtrer par type (image/video)
  setTypeFilter(type: '' | 'image' | 'video'): void {
    this.filters.type = type;
    this.currentPage = 1;
    this.loadMedias();
  }

  // Filtrer par statut
  setStatusFilter(status: 'all' | 'published' | 'scheduled' | 'pending' | 'failed'): void {
    this.filters.status = status;
    this.currentPage = 1;
    this.loadMedias();
  }

  // ============================================================
  // MÉTHODE POUR OBTENIR UNE URL SÉCURISÉE
  // ============================================================

  getSafeUrl(url: string): string {
    if (!url) {
      return 'https://placehold.co/400x300/28b188/FFFFFF?text=URL+non+disponible';
    }

    if (url.startsWith('http://') || url.startsWith('https://')) {
      return url;
    }

    if (url.startsWith('/storage/')) {
      return `https://redacteur.benin-digital.app${url}`;
    }

    if (url.startsWith('storage/')) {
      return `https://redacteur.benin-digital.app/${url}`;
    }

    if (!url.includes('http')) {
      return `https://redacteur.benin-digital.app/storage/${url.replace(/^\/?storage\//, '')}`;
    }

    return url;
  }

  // ============================================================
  // FILTRES
  // ============================================================

  applyFilters(): void {
    this.currentPage = 1;
    this.loadMedias();
  }

  resetFilters(): void {
    this.filters = { type: '', search: '', status: 'all' };
    this.currentPage = 1;
    this.loadMedias();
  }

  onTypeChange(): void {
    this.currentPage = 1;
    this.loadMedias();
  }

  onSearchChange(): void {
    if (this.filters.search.length === 0 || this.filters.search.length >= 3) {
      this.currentPage = 1;
      this.loadMedias();
    }
  }

  // ============================================================
  // PAGINATION
  // ============================================================

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages || page === this.currentPage) return;
    this.currentPage = page;
    this.loadMedias();
    document.querySelector('.dashboard-content')?.scrollTo({ top: 0, behavior: 'smooth' });
  }

  getPages(): number[] {
    const pages: number[] = [];
    const maxVisible = 5;
    let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(this.totalPages, start + maxVisible - 1);

    if (end - start < maxVisible - 1) {
      start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
      pages.push(i);
    }
    return pages;
  }

  // ============================================================
  // UPLOAD
  // ============================================================

  openUploadModal(): void {
    this.selectedFiles = [];
    this.uploadProgress = 0;
    this.showUploadModal = true;
  }

  closeUploadModal(): void {
    this.showUploadModal = false;
    this.selectedFiles = [];
    this.uploadProgress = 0;
  }

  // ============================================================
  // MÉTHODES POUR LE MODAL DE VISUALISATION
  // ============================================================

  /**
   * Récupérer le statut de publication sur une plateforme spécifique
   */
  getPublicationStatus(media: IndependentMedia, platform: 'tiktok' | 'instagram'): { published: boolean; postId?: string; postUrl?: string; publishedAt?: string } {
    const results = (media as any).publication_results || {};
    const platformData = results[platform] || {};

    // Vérifier via les champs dédiés
    const published = platform === 'tiktok'
      ? media.published_to_tiktok
      : media.published_to_instagram;

    return {
      published: published || false,
      postId: platformData.post_id || null,
      postUrl: platformData.post_url || null,
      publishedAt: platformData.published_at || null
    };
  }

  /**
   * Obtenir l'URL du post sur une plateforme
   */
  getPostUrl(media: IndependentMedia, platform: 'tiktok' | 'instagram'): string | null {
    const results = (media as any).publication_results || {};
    return results[platform]?.post_url || null;
  }

  /**
   * Obtenir l'ID du post sur une plateforme
   */
  getPostId(media: IndependentMedia, platform: 'tiktok' | 'instagram'): string | null {
    const results = (media as any).publication_results || {};
    return results[platform]?.post_id || null;
  }

  /**
   * Vérifier si le média a des résultats de publication
   */
  hasPublicationResults(media: IndependentMedia): boolean {
    const results = (media as any).publication_results;
    return results && (results.tiktok || results.instagram);
  }

  /**
   * Obtenir les plateformes sur lesquelles le média est publié
   */
  getPublishedPlatforms(media: IndependentMedia): string[] {
    const platforms: string[] = [];
    if (media.published_to_tiktok) platforms.push('tiktok');
    if (media.published_to_instagram) platforms.push('instagram');
    return platforms;
  }

  // ============================================================
  // MÉTHODES POUR LE MODAL DE PARTAGE SOCIAL
  // ============================================================

  /**
   * Vérifier si le média est déjà publié sur une plateforme
   */
  isAlreadyPublishedOnPlatform(platform: string): boolean {
    if (!this.mediaToShare) return false;
    if (platform === 'tiktok') return this.mediaToShare.published_to_tiktok || false;
    if (platform === 'instagram') return this.mediaToShare.published_to_instagram || false;
    return false;
  }

  /**
   * Vérifier si le média est déjà publié sur toutes les plateformes
   */
  isAlreadyPublishedOnAllPlatforms(): boolean {
    if (!this.mediaToShare) return false;
    return !!(this.mediaToShare.published_to_tiktok && this.mediaToShare.published_to_instagram);
  }

  /**
   * Obtenir le libellé d'une plateforme
   */
  getPlatformDisplayName(platform: string): string {
    const names: Record<string, string> = {
      'tiktok': 'TikTok',
      'instagram': 'Instagram'
    };
    return names[platform] || platform;
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files) {
      for (let i = 0; i < input.files.length; i++) {
        this.selectedFiles.push(input.files[i]);
      }
    }
  }

  removeFile(index: number): void {
    this.selectedFiles.splice(index, 1);
  }

  uploadFiles(): void {
    if (this.selectedFiles.length === 0) return;

    this.isUploadingFile = true;
    this.uploadProgress = 0;
    this.errorMessage = null;

    this.managerService.uploadMultipleMedia(this.selectedFiles).subscribe({
      next: (response) => {
        this.uploadProgress = 100;
        this.successMessage = response.message || `${this.selectedFiles.length} fichier(s) uploadé(s) avec succès !`;
        setTimeout(() => this.successMessage = null, 4000);
        this.isUploadingFile = false;
        this.closeUploadModal();
        this.loadMedias();
      },
      error: (error) => {
        console.error('Erreur upload:', error);
        this.errorMessage = error.message || 'Erreur lors de l\'upload';
        setTimeout(() => this.errorMessage = null, 4000);
        this.isUploadingFile = false;
      }
    });
  }

  // ============================================================
  // SHARE / PUBLICATION SOCIALE
  // ============================================================

  openShareModal(media: IndependentMedia, event: Event): void {
    event.stopPropagation();
    this.mediaToShare = media;
    this.sharePlatform = '';
    this.shareCaption = '';
    this.scheduleDateTime = '';
    this.showScheduleShare = false;
    this.compatibilityMessage = null;
    this.isCompatible = true;
    this.showShareModal = true;
  }

  closeShareModal(): void {
    this.showShareModal = false;
    this.mediaToShare = null;
    this.sharePlatform = '';
    this.shareCaption = '';
    this.isPublishing = false;
    this.showScheduleShare = false;
    this.compatibilityMessage = null;
    this.isCompatible = true;
  }

  // ============================================================
  // ANNULATION DE PROGRAMMATION AVEC MODAL DE CONFIRMATION
  // ============================================================

  /**
   * Ouvrir le modal de confirmation d'annulation
   */
  openCancelConfirmation(): void {
    this.showCancelConfirmationModal = true;
  }

  /**
   * Fermer le modal de confirmation d'annulation
   */
  closeCancelConfirmation(): void {
    this.showCancelConfirmationModal = false;
    this.isCancelling = false;
  }

  /**
   * Confirmer l'annulation de la programmation
   */
  confirmCancelSchedule(): void {
    if (!this.selectedMedia) return;

    this.isCancelling = true;
    this.errorMessage = null;

    // Récupérer la tâche
    this.managerService.getScheduledTasks().subscribe({
      next: (response) => {
        const task = response.tasks.find(
          t => t.media_id === this.selectedMedia?.id && t.status === 'pending'
        );

        if (task) {
          this.managerService.deleteScheduledTask(task.id).subscribe({
            next: (response) => {
              this.successMessage = 'Programmation annulée avec succès !';
              this.closeCancelConfirmation();
              this.closeMediaModal(); // Fermer le modal de visualisation
              this.loadMedias(); // Recharger les médias
              setTimeout(() => this.successMessage = null, 4000);
              this.isCancelling = false;
            },
            error: (error) => {
              this.errorMessage = error.message || 'Erreur lors de l\'annulation';
              setTimeout(() => this.errorMessage = null, 4000);
              this.isCancelling = false;
            }
          });
        } else {
          this.errorMessage = 'Aucune programmation trouvée';
          setTimeout(() => this.errorMessage = null, 3000);
          this.isCancelling = false;
        }
      },
      error: (error) => {
        this.errorMessage = 'Erreur lors de la récupération de la programmation';
        setTimeout(() => this.errorMessage = null, 3000);
        this.isCancelling = false;
      }
    });
  }

  /**
   * Annuler la programmation (méthode appelée par le bouton dans le modal)
   */
  cancelScheduledTaskFromModal(): void {
    // Ouvrir le modal de confirmation au lieu d'utiliser alert()
    this.openCancelConfirmation();
  }

  schedulePublication(): void {
    if (!this.mediaToShare || !this.sharePlatform || !this.scheduleDateTime) {
      return;
    }

    this.isPublishing = true;
    this.errorMessage = null;
    this.successMessage = null;

    const payload: ScheduleMediaPayload = {
      platform: this.sharePlatform as 'tiktok' | 'instagram',
      scheduled_at: this.scheduleDateTime,
      caption: this.shareCaption
    };

    this.managerService.scheduleMediaPublication(this.mediaToShare.id, payload).subscribe({
      next: (response) => {
        this.isPublishing = false;
        this.closeShareModal();
        this.loadMedias();
        this.successMessage = response.message || `Publication programmée sur ${this.sharePlatform} pour le ${new Date(this.scheduleDateTime).toLocaleString('fr-FR')}`;
        setTimeout(() => {
          this.successMessage = null;
        }, 8000);
        if (this.showMediaModal) {
          this.closeMediaModal();
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
      error: (error) => {
        this.isPublishing = false;
        // Récupérer le message d'erreur personnalisé
        const errorMsg = error.error?.message || error.error?.error || error.message || 'Erreur lors de la programmation';
        this.errorMessage = errorMsg;
        setTimeout(() => {
          this.errorMessage = null;
        }, 8000);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  // ============================================================
  // ACTIONS SUR LES MÉDIAS
  // ============================================================

  openMediaModal(media: IndependentMedia): void {
    this.selectedMedia = media;
    this.showMediaModal = true;
  }

  closeMediaModal(): void {
    this.showMediaModal = false;
    this.selectedMedia = null;
  }

  openDeleteModal(media: IndependentMedia, event: Event): void {
    event.stopPropagation();
    this.mediaToDelete = media;
    this.showDeleteModal = true;
  }

  closeDeleteModal(): void {
    this.showDeleteModal = false;
    this.mediaToDelete = null;
    this.isDeleting = false;
  }

  confirmDelete(): void {
    if (!this.mediaToDelete) return;

    this.isDeleting = true;
    this.errorMessage = null;

    this.managerService.deleteIndependentMedia(this.mediaToDelete.id).subscribe({
      next: (response) => {
        this.successMessage = response.message || 'Média supprimé avec succès';
        setTimeout(() => this.successMessage = null, 4000);
        this.closeDeleteModal();
        this.loadMedias();
        this.isDeleting = false;
      },
      error: (error) => {
        console.error('Erreur suppression:', error);
        this.errorMessage = error.message || 'Erreur lors de la suppression';
        setTimeout(() => this.errorMessage = null, 4000);
        this.isDeleting = false;
      }
    });
  }

  downloadMedia(media: IndependentMedia, event: Event): void {
    event.stopPropagation();
    if (media.url) {
      window.open(media.url, '_blank');
    } else {
      this.errorMessage = 'Téléchargement non disponible';
      setTimeout(() => this.errorMessage = null, 3000);
    }
  }

  // ============================================================
  // GESTION DE LA PROGRAMMATION DANS LE MODAL
  // ============================================================

  /**
   * Ouvrir le modal de modification de programmation
   */
  openEditScheduleModal(): void {
    if (!this.selectedMedia) return;

    // Récupérer la tâche programmée pour ce média
    this.managerService.getScheduledTasks().subscribe({
      next: (response) => {
        const task = response.tasks.find(
          t => t.media_id === this.selectedMedia?.id && t.status === 'pending'
        );

        if (task) {
          this.selectedTaskId = task.id;
          // Pré-remplir avec la date actuelle
          const date = new Date(task.scheduled_at);
          this.editScheduleDateTime = date.toISOString().slice(0, 16);
          this.editScheduleError = null;
          this.showEditScheduleModal = true;
        } else {
          this.errorMessage = 'Aucune programmation trouvée pour ce média';
          setTimeout(() => this.errorMessage = null, 3000);
        }
      },
      error: (error) => {
        this.errorMessage = 'Erreur lors du chargement de la programmation';
        setTimeout(() => this.errorMessage = null, 3000);
      }
    });
  }

  /**
   * Fermer le modal de modification
   */
  closeEditScheduleModal(): void {
    this.showEditScheduleModal = false;
    this.editScheduleDateTime = '';
    this.editScheduleError = null;
    this.isUpdatingSchedule = false;
    this.selectedTaskId = null;
  }

  publishToSocial(): void {
    if (!this.mediaToShare || !this.sharePlatform) return;

    this.isPublishing = true;
    this.errorMessage = null;
    this.successMessage = null;

    const url = `${environment.apiUrl}/social/${this.sharePlatform}/publish`;

    const payload: any = {
      media_id: this.mediaToShare.id,
      caption: this.shareCaption
    };

    if (this.sharePlatform === 'tiktok') {
      payload.title = this.shareCaption;
      payload.privacy = 'PUBLIC_TO_EVERYONE';
    }

    this.http.post(url, payload).subscribe({
      next: (response: any) => {
        this.isPublishing = false;

        // Fermer le modal de partage
        this.closeShareModal();

        // Charger les médias pour mettre à jour la liste
        this.loadMedias();

        // Afficher le message de succès
        this.successMessage = response.message || `Publié sur ${this.sharePlatform} avec succès !`;

        // Doubler le temps d'affichage (8 secondes au lieu de 4)
        setTimeout(() => {
          this.successMessage = null;
        }, 8000);

        // Fermer le modal de visualisation si ouvert
        if (this.showMediaModal) {
          this.closeMediaModal();
        }

        // Scroll en haut de la page
        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
      error: (error) => {
        this.isPublishing = false;
        this.errorMessage = error.error?.error || error.error?.message || 'Erreur lors de la publication';

        // Doubler le temps d'affichage de l'erreur (8 secondes)
        setTimeout(() => {
          this.errorMessage = null;
        }, 8000);

        // Scroll en haut de la page
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /**
   * Recadrer automatiquement l'image pour qu'elle soit compatible Instagram
   * (Fonctionnalité simple : ouvre un éditeur ou télécharge une version recadrée)
   */

  autoResizeMedia(): void {
    // Vérification explicite que mediaToShare n'est pas null
    if (!this.mediaToShare) {
      this.errorMessage = 'Aucun média sélectionné.';
      setTimeout(() => this.errorMessage = null, 3000);
      return;
    }

    this.isPublishing = true;
    this.errorMessage = null;

    // Appeler l'API de recadrage
    const url = `${environment.apiUrl}/media/${this.mediaToShare.id}/crop-to-instagram`;

    this.http.post(url, {}).subscribe({
      next: (response: any) => {
        this.isPublishing = false;
        this.successMessage = 'Image recadrée avec succès pour Instagram !';
        setTimeout(() => this.successMessage = null, 3000);
        this.loadMedias();
        // Mettre à jour le média avec la nouvelle URL
        if (response.media && this.mediaToShare) {
          this.mediaToShare = { ...this.mediaToShare, ...response.media };
        }
      },
      error: () => {
        this.isPublishing = false;
        // Utilisation de l'opérateur de coalescence ou de vérification
        if (this.mediaToShare?.url) {
          window.open(this.mediaToShare.url, '_blank');
          this.errorMessage = '🔧 Ouvrez l\'image dans un nouvel onglet et recadrez-la en 1:1 (carré) ou 4:5 (portrait)';
        } else {
          this.errorMessage = 'Impossible de recadrer l\'image. URL non disponible.';
        }
        setTimeout(() => this.errorMessage = null, 5000);
      }
    });
  }

  /**
   * Vérifier si un média est compatible avec Instagram
   */

  checkInstagramCompatibility(media: IndependentMedia | null): { compatible: boolean; message?: string } {
    // Vérification que media n'est pas null
    if (!media) {
      return { compatible: true };
    }

    if (media.type === 'video') {
      if (media.duration_seconds && media.duration_seconds > 60) {
        return {
          compatible: false,
          message: `La vidéo dure ${media.duration_seconds}s. Instagram accepte maximum 60s.`
        };
      }
      if (media.width && media.height) {
        const ratio = media.width / media.height;
        if (ratio < 0.5 || ratio > 0.6) {
          return {
            compatible: false,
            message: `Ratio ${ratio.toFixed(2)} non recommandé. Utilisez 9:16 (portrait) pour les Reels.`
          };
        }
      }
      return { compatible: true };
    }

    if (media.type === 'image') {
      if (!media.width || !media.height) {
        return { compatible: true };
      }

      const ratio = media.width / media.height;
      const supportedRatios = [
        { name: '1:1 (carré)', min: 0.98, max: 1.02 },
        { name: '4:5 (portrait)', min: 0.78, max: 0.82 },
        { name: '1.91:1 (paysage)', min: 1.88, max: 1.94 }
      ];

      for (const r of supportedRatios) {
        if (ratio >= r.min && ratio <= r.max) {
          return { compatible: true };
        }
      }

      return {
        compatible: false,
        message: `Ratio ${ratio.toFixed(2)} non supporté. Utilisez 1:1, 4:5 ou 1.91:1.`
      };
    }

    return { compatible: true };
  }

  /**
   * Mettre à jour la programmation
   */
  updateSchedule(): void {
    if (!this.selectedTaskId) return;

    if (!this.editScheduleDateTime) {
      this.editScheduleError = 'Veuillez sélectionner une date et heure';
      return;
    }

    // Vérifier que la date est dans le futur
    const selectedDate = new Date(this.editScheduleDateTime);
    const now = new Date();
    if (selectedDate <= now) {
      this.editScheduleError = 'La date de publication doit être dans le futur.';
      return;
    }

    this.isUpdatingSchedule = true;
    this.editScheduleError = null;

    this.managerService.updateScheduledTask(this.selectedTaskId, {
      scheduled_at: this.editScheduleDateTime
    }).subscribe({
      next: (response) => {
        this.successMessage = 'Programmation modifiée avec succès !';
        this.closeEditScheduleModal();
        this.loadMedias(); // Recharger les médias
        setTimeout(() => this.successMessage = null, 4000);
        this.isUpdatingSchedule = false;
      },
      error: (error) => {
        this.editScheduleError = error.message || 'Erreur lors de la modification';
        this.isUpdatingSchedule = false;
        setTimeout(() => this.editScheduleError = null, 4000);
      }
    });
  }

  // ============================================================
  // UTILITAIRES
  // ============================================================

  getMediaType(media: IndependentMedia): 'image' | 'video' {
    if (media.type === 'video') {
      return 'video';
    }
    if (media.type === 'image') {
      return 'image';
    }
    // Fallback: si duration_seconds est présent, c'est une vidéo
    if (media.duration_seconds && media.duration_seconds > 0) {
      return 'video';
    }
    return 'image';
  }

  getMediaIcon(media: IndependentMedia): string {
    return this.getMediaType(media) === 'image' ? 'bi-image' : 'bi-film';
  }

  getMediaTypeLabel(media: IndependentMedia): string {
    return this.getMediaType(media) === 'image' ? 'Image' : 'Vidéo';
  }

  getFileSize(size: number): string {
    if (!size || size === 0) return '0 Mo';

    const units = ['octets', 'Ko', 'Mo', 'Go', 'To'];
    let i = 0;
    let fileSize = size;

    while (fileSize >= 1024 && i < units.length - 1) {
      fileSize /= 1024;
      i++;
    }

    if (i === 0) {
      return `${Math.round(fileSize)} ${units[i]}`;
    }

    return `${fileSize.toFixed(1)} ${units[i]}`;
  }

  get minScheduleDate(): string {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 30);
    return now.toISOString().slice(0, 16);
  }

  getFormattedDate(date: string): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  getPlatformLabel(platform: string): string {
    const labels: Record<string, string> = {
      'tiktok': 'TikTok',
      'instagram': 'Instagram',
    };
    return labels[platform] || platform;
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      'pending': 'En attente',
      'processing': 'En cours',
      'executed': 'Exécutée',
      'failed': 'Échouée',
      'cancelled': 'Annulée'
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: Record<string, string> = {
      'pending': 'status-pending',
      'processing': 'status-processing',
      'executed': 'status-executed',
      'failed': 'status-failed',
      'cancelled': 'status-cancelled'
    };
    return classes[status] || '';
  }

  get greeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  get firstName(): string {
    return this.currentUser?.firstname ?? this.currentUser?.name ?? 'Utilisateur';
  }

  toggleSidebar(): void {
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }

  get isEmpty(): boolean {
    return !this.isLoading && this.medias.length === 0;
  }
}