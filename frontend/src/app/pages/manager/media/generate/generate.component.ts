// media/generate/generate.component.ts

import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ManagerService, IndependentMedia, IndependentImageResponse, IndependentVideoResponse, ScheduleMediaPayload, ActiveProvider, ActiveProvidersResponse } from '../../../../core/services/manager.service';
import { AuthService, User } from '../../../../core/services/auth.service';
import { SidebarComponent } from '../../../../shared/components/sidebar/sidebar/sidebar.component';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

@Component({
  selector: 'app-generate',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule, SidebarComponent],
  templateUrl: './generate.component.html',
  styleUrl: './generate.component.css'
})
export class GenerateComponent implements OnInit {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = false;
  isGenerating = false;
  isLoadingProviders = false;
  errorMessage: string | null = null;
  successMessage: string | null = null;

  // Onglet actif
  activeTab: 'image' | 'video' = 'image';

  // Formulaires
  imageForm: FormGroup;
  videoForm: FormGroup;

  // Résultats
  generatedImages: IndependentMedia[] = [];
  generatedVideo: IndependentMedia | null = null;

  showEditScheduleModal = false;
  editScheduleDateTime: string = '';
  isUpdatingSchedule = false;
  editScheduleError: string | null = null;
  selectedTaskId: number | null = null;
  isCancelling = false;

  compatibilityMessage: string | null = null;
  isCompatible: boolean = true;

  // Progression
  showProgress = false;
  progressValue = 0;
  progressMessage = '';

  // Providers disponibles
  availableProviders: ActiveProvider[] = [];
  selectedProviderId: number | null = null;

  // Aspect ratios
  aspectRatios = [
    { value: '16:9', label: '16:9 - Paysage' },
    { value: '9:16', label: '9:16 - Portrait' },
    { value: '1:1', label: '1:1 - Carré' }
  ];

  durations = [
    { value: 5, label: '5 secondes' },
    { value: 8, label: '8 secondes' },
    { value: 15, label: '15 secondes' },
    { value: 30, label: '30 secondes' }
  ];

  imageCounts = [1, 2, 3, 4];

  // ============================================================
  // PUBLICATION / PARTAGE
  // ============================================================

  showPublishModal = false;
  mediaToPublish: IndependentMedia | null = null;
  publishPlatform: string = '';
  publishCaption: string = '';
  isPublishing = false;
  showSchedulePublish = false;
  publishScheduleDateTime: string = '';

  // ============================================================
  // SUPPRESSION
  // ============================================================

  showDeleteModal = false;
  mediaToDelete: IndependentMedia | null = null;
  isDeleting = false;

  showMediaModal = false;
  selectedMedia: IndependentMedia | null = null;

  constructor(
    private fb: FormBuilder,
    private managerService: ManagerService,
    private authService: AuthService,
    private http: HttpClient
  ) {
    this.imageForm = this.createImageForm();
    this.videoForm = this.createVideoForm();
  }

  // la méthode onPlatformChange (ou modifier publishPlatform)
  onPlatformChange(platform: string): void {
    this.publishPlatform = platform;

    // Réinitialiser les messages
    this.compatibilityMessage = null;
    this.isCompatible = true;

    // Vérifier la compatibilité (pour Instagram uniquement)
    if (platform === 'instagram') {
      this.checkCompatibilityAndShowAlert();
    }
  }

  // la méthode checkCompatibilityAndShowAlert
  checkCompatibilityAndShowAlert(): void {
    if (!this.mediaToPublish || this.publishPlatform !== 'instagram') {
      this.compatibilityMessage = null;
      this.isCompatible = true;
      return;
    }

    const result = this.checkInstagramCompatibility(this.mediaToPublish);
    if (!result.compatible) {
      this.isCompatible = false;
      this.compatibilityMessage = result.message || 'Format non compatible avec Instagram.';
    } else {
      this.isCompatible = true;
      this.compatibilityMessage = null;
    }
  }

  /**
 * Formater une taille de fichier à partir d'un nombre
 */
  getFileSizeForNumber(size: number): string {
    if (!size) return '0 KB';
    if (size < 1024) return `${size} KB`;
    return `${(size / 1024).toFixed(1)} MB`;
  }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    // Charger les providers pour l'onglet actif
    this.loadActiveProviders('image');
  }

  // ============================================================
  // SCROLL VERS LE HAUT
  // ============================================================

  /**
   * Scroll vers le haut de la page
   */
  private scrollToTop(): void {
    const dashboardContent = document.querySelector('.dashboard-content');
    if (dashboardContent) {
      dashboardContent.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }
  }

  private createImageForm(): FormGroup {
    return this.fb.group({
      prompt: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(1000)]],
      provider_id: ['', Validators.required],
      aspect_ratio: ['16:9'],
      style: ['moderne, professionnel'],
      count: [1, [Validators.min(1), Validators.max(4)]]
    });
  }

  private createVideoForm(): FormGroup {
    return this.fb.group({
      prompt: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(1000)]],
      provider_id: ['', Validators.required],
      duration: [8],
      aspect_ratio: ['16:9'],
      style: ['cinématographique, professionnel']
    });
  }

  /**
 * Ouvrir le modal de visualisation
 */
  openMediaModal(media: IndependentMedia): void {
    this.selectedMedia = media;
    this.showMediaModal = true;
  }

  /**
   * Fermer le modal de visualisation
   */
  closeMediaModal(): void {
    this.showMediaModal = false;
    this.selectedMedia = null;
  }

  /**
   * Obtenir l'icône du média
   */
  getMediaIcon(media: IndependentMedia): string {
    return this.getMediaType(media) === 'image' ? 'bi-image' : 'bi-film';
  }

  /**
   * Formater une date
   */
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

  // ============================================================
  // VÉRIFICATION DE COMPATIBILITÉ INSTAGRAM
  // ============================================================

  /**
   * Vérifier si un média est compatible avec Instagram
   */
  checkInstagramCompatibility(media: IndependentMedia | null): { compatible: boolean; message?: string } {
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
   * Ouvrir le modal de modification de programmation
   */
  openEditScheduleModal(): void {
    if (!this.selectedMedia) return;

    this.managerService.getScheduledTasks().subscribe({
      next: (response) => {
        const task = response.tasks.find(
          t => t.media_id === this.selectedMedia?.id && t.status === 'pending'
        );

        if (task) {
          this.selectedTaskId = task.id;
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

  /**
   * Mettre à jour la programmation
   */
  updateSchedule(): void {
    if (!this.selectedTaskId) return;

    if (!this.editScheduleDateTime) {
      this.editScheduleError = 'Veuillez sélectionner une date et heure';
      return;
    }

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
        this.refreshResults();
        setTimeout(() => this.successMessage = null, 4000);
        this.isUpdatingSchedule = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
      error: (error) => {
        this.editScheduleError = error.message || 'Erreur lors de la modification';
        this.isUpdatingSchedule = false;
        setTimeout(() => this.editScheduleError = null, 4000);
      }
    });
  }

  /**
   * Annuler la programmation depuis le modal
   */
  cancelScheduledTaskFromModal(): void {
    if (!this.selectedMedia) return;

    this.isCancelling = true;
    this.errorMessage = null;

    this.managerService.getScheduledTasks().subscribe({
      next: (response) => {
        const task = response.tasks.find(
          t => t.media_id === this.selectedMedia?.id && t.status === 'pending'
        );

        if (task) {
          this.managerService.deleteScheduledTask(task.id).subscribe({
            next: (response) => {
              this.successMessage = 'Programmation annulée avec succès !';
              this.closeMediaModal();
              this.refreshResults();
              setTimeout(() => this.successMessage = null, 4000);
              this.isCancelling = false;
              window.scrollTo({ top: 0, behavior: 'smooth' });
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
   * Obtenir les détails de compatibilité
   */
  getCompatibilityDetails(media: IndependentMedia | null, platform: string): string {
    if (!media) return '';

    if (platform === 'instagram' && media.type === 'image' && media.width && media.height) {
      const ratio = media.width / media.height;
      const ratioStr = ratio.toFixed(2);
      const dimensions = `${media.width}x${media.height}`;

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

  // ============================================================
  // GESTION DES TABS
  // ============================================================

  setActiveTab(tab: 'image' | 'video'): void {
    this.activeTab = tab;
    // Réinitialiser les résultats lors du changement d'onglet
    if (tab === 'image') {
      this.generatedImages = [];
    } else {
      this.generatedVideo = null;
    }
    this.errorMessage = null;
    this.successMessage = null;

    // Charger les providers pour le nouveau type
    this.loadActiveProviders(tab);

    // Scroll vers le haut après changement d'onglet
    setTimeout(() => {
      this.scrollToTop();
    }, 100);
  }

  // ============================================================
  // CHARGEMENT DES PROVIDERS
  // ============================================================

  loadActiveProviders(type: 'image' | 'video'): void {
    this.isLoadingProviders = true;
    this.availableProviders = [];
    this.selectedProviderId = null;

    // Réinitialiser le champ provider_id dans le formulaire actif
    if (type === 'image') {
      this.imageForm.get('provider_id')?.setValue('');
      this.imageForm.get('provider_id')?.markAsUntouched();
    } else {
      this.videoForm.get('provider_id')?.setValue('');
      this.videoForm.get('provider_id')?.markAsUntouched();
    }

    this.managerService.getActiveProviders(type).subscribe({
      next: (response: ActiveProvidersResponse) => {
        this.availableProviders = response.providers || [];
        this.isLoadingProviders = false;

        // Si un seul provider, le sélectionner automatiquement
        if (this.availableProviders.length === 1) {
          const provider = this.availableProviders[0];
          this.selectedProviderId = provider.id;
          if (type === 'image') {
            this.imageForm.get('provider_id')?.setValue(provider.id);
          } else {
            this.videoForm.get('provider_id')?.setValue(provider.id);
          }
        }

        // Si aucun provider, afficher un message d'erreur
        if (this.availableProviders.length === 0) {
          this.errorMessage = `Aucun modèle IA actif pour la génération de ${type === 'image' ? 'images' : 'vidéos'}. Contactez l'administrateur.`;
          setTimeout(() => this.errorMessage = null, 5000);
        }
      },
      error: (error) => {
        this.isLoadingProviders = false;
        this.errorMessage = error.message || 'Erreur lors du chargement des modèles disponibles';
        setTimeout(() => this.errorMessage = null, 5000);
      }
    });
  }

  onProviderChange(): void {
    const form = this.activeTab === 'image' ? this.imageForm : this.videoForm;
    const providerId = form.get('provider_id')?.value;
    this.selectedProviderId = providerId ? parseInt(providerId) : null;
  }

  getSelectedProvider(): ActiveProvider | undefined {
    return this.availableProviders.find(p => p.id === this.selectedProviderId);
  }

  // ============================================================
  // GÉNÉRATION D'IMAGES
  // ============================================================

  generateImages(): void {
    if (this.imageForm.invalid) {
      Object.keys(this.imageForm.controls).forEach(key => {
        this.imageForm.get(key)?.markAsTouched();
      });
      return;
    }

    const providerId = this.imageForm.get('provider_id')?.value;
    const selectedProvider = this.availableProviders.find(p => p.id === parseInt(providerId));

    if (!selectedProvider) {
      this.errorMessage = 'Veuillez sélectionner un modèle IA';
      setTimeout(() => this.errorMessage = null, 3000);
      return;
    }

    this.isGenerating = true;
    this.errorMessage = null;
    this.successMessage = null;
    this.generatedImages = [];

    // Démarrer la progression
    this.showProgress = true;
    this.progressValue = 0;
    this.progressMessage = 'Préparation de la génération...';

    const progressInterval = setInterval(() => {
      if (this.progressValue < 90) {
        this.progressValue += 15;
        if (this.progressValue === 30) this.progressMessage = 'Analyse du prompt...';
        if (this.progressValue === 50) this.progressMessage = `Génération avec ${selectedProvider.label}...`;
        if (this.progressValue === 70) this.progressMessage = 'Optimisation...';
        if (this.progressValue === 85) this.progressMessage = 'Finalisation...';
      }
    }, 500);

    const payload = {
      prompt: this.imageForm.value.prompt.trim(),
      aspect_ratio: this.imageForm.value.aspect_ratio,
      style: this.imageForm.value.style,
      count: this.imageForm.value.count,
      provider: selectedProvider.provider
    };

    this.managerService.generateIndependentImages(payload).subscribe({
      next: (response: IndependentImageResponse) => {
        clearInterval(progressInterval);
        this.progressValue = 100;
        this.progressMessage = 'Génération terminée !';

        setTimeout(() => {
          this.showProgress = false;
          this.progressValue = 0;
        }, 800);

        if (response.success) {
          this.generatedImages = response.images || [];
          this.generatedImages = this.generatedImages.map(img => ({
            ...img,
            is_published: img.is_published || false,
            is_scheduled: img.is_scheduled || false,
            is_failed: img.is_failed || false,
            is_pending: img.is_pending || true,
            provider: selectedProvider.provider,
            model_used: selectedProvider.model_name || undefined
          }));
          this.successMessage = response.message || `${this.generatedImages.length} image(s) générée(s) avec ${selectedProvider.label} !`;
          setTimeout(() => this.successMessage = null, 5000);

          // Scroll vers le haut après génération
          setTimeout(() => {
            this.scrollToTop();
          }, 100);
        } else {
          this.errorMessage = response.message || 'Erreur lors de la génération';
          setTimeout(() => this.errorMessage = null, 5000);
        }
        this.isGenerating = false;
      },
      error: (error) => {
        clearInterval(progressInterval);
        this.showProgress = false;
        this.progressValue = 0;
        this.errorMessage = error.message || 'Erreur lors de la génération des images';
        setTimeout(() => this.errorMessage = null, 5000);
        this.isGenerating = false;
      }
    });
  }

  // ============================================================
  // GÉNÉRATION DE VIDÉO
  // ============================================================

  generateVideo(): void {
    if (this.videoForm.invalid) {
      Object.keys(this.videoForm.controls).forEach(key => {
        this.videoForm.get(key)?.markAsTouched();
      });
      return;
    }

    const providerId = this.videoForm.get('provider_id')?.value;
    const selectedProvider = this.availableProviders.find(p => p.id === parseInt(providerId));

    if (!selectedProvider) {
      this.errorMessage = 'Veuillez sélectionner un modèle IA';
      setTimeout(() => this.errorMessage = null, 3000);
      return;
    }

    this.isGenerating = true;
    this.errorMessage = null;
    this.successMessage = null;
    this.generatedVideo = null;

    // Démarrer la progression
    this.showProgress = true;
    this.progressValue = 0;
    this.progressMessage = 'Préparation de la génération...';

    const progressInterval = setInterval(() => {
      if (this.progressValue < 90) {
        this.progressValue += 15;
        if (this.progressValue === 30) this.progressMessage = 'Analyse du prompt...';
        if (this.progressValue === 50) this.progressMessage = `Génération avec ${selectedProvider.label}...`;
        if (this.progressValue === 70) this.progressMessage = 'Rendu...';
        if (this.progressValue === 85) this.progressMessage = 'Finalisation...';
      }
    }, 800);

    const payload = {
      prompt: this.videoForm.value.prompt.trim(),
      duration: this.videoForm.value.duration,
      aspect_ratio: this.videoForm.value.aspect_ratio,
      style: this.videoForm.value.style,
      provider: selectedProvider.provider
    };

    this.managerService.generateIndependentVideo(payload).subscribe({
      next: (response: IndependentVideoResponse) => {
        clearInterval(progressInterval);
        this.progressValue = 100;
        this.progressMessage = 'Génération terminée !';

        setTimeout(() => {
          this.showProgress = false;
          this.progressValue = 0;
        }, 800);

        if (response.success) {
          this.generatedVideo = response.video || null;
          if (this.generatedVideo) {
            this.generatedVideo.is_published = false;
            this.generatedVideo.is_scheduled = false;
            this.generatedVideo.is_failed = false;
            this.generatedVideo.is_pending = true;
            this.generatedVideo.provider = selectedProvider.provider;
            this.generatedVideo.model_used = selectedProvider.model_name || undefined;
          }
          this.successMessage = response.message || `Vidéo générée avec ${selectedProvider.label} !`;
          setTimeout(() => this.successMessage = null, 5000);

          // Scroll vers le haut après génération
          setTimeout(() => {
            this.scrollToTop();
          }, 100);
        } else {
          this.errorMessage = response.message || 'Erreur lors de la génération';
          setTimeout(() => this.errorMessage = null, 5000);
        }
        this.isGenerating = false;
      },
      error: (error) => {
        clearInterval(progressInterval);
        this.showProgress = false;
        this.progressValue = 0;
        this.errorMessage = error.message || 'Erreur lors de la génération de la vidéo';
        setTimeout(() => this.errorMessage = null, 5000);
        this.isGenerating = false;
      }
    });
  }

  // ============================================================
  // PUBLICATION / PARTAGE
  // ============================================================

  /**
   * Ouvrir le modal de publication
   */
  openPublishModal(media: IndependentMedia): void {
    this.mediaToPublish = media;
    this.publishPlatform = '';
    this.publishCaption = '';
    this.publishScheduleDateTime = '';
    this.showSchedulePublish = false;
    this.isPublishing = false;
    this.showPublishModal = true;
  }

  /**
   * Fermer le modal de publication
   */
  closePublishModal(): void {
    this.showPublishModal = false;
    this.mediaToPublish = null;
    this.publishPlatform = '';
    this.publishCaption = '';
    this.isPublishing = false;
    this.showSchedulePublish = false;
  }

  // ============================================================
  // MÉTHODES POUR LE MODAL DE PARTAGE SOCIAL
  // ============================================================

  /**
   * Vérifier si le média est déjà publié sur une plateforme
   */
  isAlreadyPublishedOnPlatform(platform: string): boolean {
    if (!this.mediaToPublish) return false;
    if (platform === 'tiktok') return this.mediaToPublish.published_to_tiktok || false;
    if (platform === 'instagram') return this.mediaToPublish.published_to_instagram || false;
    return false;
  }

  /**
   * Obtenir le type de média (image/video)
   */
  getMediaType(media: IndependentMedia): 'image' | 'video' {
    if (media.type === 'video') {
      return 'video';
    }
    if (media.type === 'image') {
      return 'image';
    }
    if (media.duration_seconds && media.duration_seconds > 0) {
      return 'video';
    }
    return 'image';
  }

  /**
   * Obtenir le libellé du type de média
   */
  getMediaTypeLabel(media: IndependentMedia): string {
    return this.getMediaType(media) === 'image' ? 'Image' : 'Vidéo';
  }

  publishNow(): void {
    if (!this.mediaToPublish || !this.publishPlatform) return;

    this.isPublishing = true;
    this.errorMessage = null;
    this.successMessage = null;

    const url = `${environment.apiUrl}/social/${this.publishPlatform}/publish`;

    const payload: any = {
      media_id: this.mediaToPublish.id,
      caption: this.publishCaption
    };

    if (this.publishPlatform === 'tiktok') {
      payload.title = this.publishCaption;
      payload.privacy = 'PUBLIC_TO_EVERYONE';
    }

    this.http.post(url, payload).subscribe({
      next: (response: any) => {
        this.isPublishing = false;
        this.successMessage = response.message || `Publié sur ${this.publishPlatform} avec succès !`;

        // Mettre à jour le statut du média
        if (this.mediaToPublish) {
          if (this.publishPlatform === 'tiktok') {
            this.mediaToPublish.published_to_tiktok = true;
          } else if (this.publishPlatform === 'instagram') {
            this.mediaToPublish.published_to_instagram = true;
          }
          this.mediaToPublish.is_published = true;
          this.mediaToPublish.is_pending = false;
          this.mediaToPublish.published_at = new Date().toISOString();
        }

        this.closePublishModal();
        this.refreshResults();

        setTimeout(() => {
          this.successMessage = null;
        }, 8000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
      error: (error) => {
        this.isPublishing = false;
        this.errorMessage = error.error?.error || error.error?.message || 'Erreur lors de la publication';

        setTimeout(() => {
          this.errorMessage = null;
        }, 8000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /**
   * Programmer une publication
   */
  schedulePublication(): void {
    if (!this.mediaToPublish || !this.publishPlatform || !this.publishScheduleDateTime) {
      return;
    }

    this.isPublishing = true;
    this.errorMessage = null;
    this.successMessage = null;

    const payload: ScheduleMediaPayload = {
      platform: this.publishPlatform as 'tiktok' | 'instagram',
      scheduled_at: this.publishScheduleDateTime,
      caption: this.publishCaption
    };

    this.managerService.scheduleMediaPublication(this.mediaToPublish.id, payload).subscribe({
      next: (response) => {
        this.isPublishing = false;
        this.successMessage = response.message || `📅 Publication programmée sur ${this.publishPlatform} pour le ${new Date(this.publishScheduleDateTime).toLocaleString('fr-FR')}`;

        // Mettre à jour le statut du média
        if (this.mediaToPublish) {
          this.mediaToPublish.is_published = false;
          this.mediaToPublish.is_scheduled = true;
          this.mediaToPublish.is_pending = false;
        }

        this.closePublishModal();
        this.refreshResults();

        setTimeout(() => {
          this.successMessage = null;
        }, 8000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
      error: (error) => {
        this.isPublishing = false;
        const errorMsg = error.error?.message || error.error?.error || error.message || 'Erreur lors de la programmation';
        this.errorMessage = errorMsg;

        setTimeout(() => {
          this.errorMessage = null;
        }, 8000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /**
   * Rafraîchir les résultats après publication
   */
  private refreshResults(): void {
    if (this.activeTab === 'image' && this.generatedImages.length > 0) {
      // On pourrait re-fetch les médias, mais on garde les données locales
    }
  }

  // ============================================================
  // SUPPRESSION
  // ============================================================

  /**
   * Ouvrir le modal de suppression
   */
  openDeleteModal(media: IndependentMedia, event: Event): void {
    event.stopPropagation();
    this.mediaToDelete = media;
    this.showDeleteModal = true;
  }

  /**
   * Fermer le modal de suppression
   */
  closeDeleteModal(): void {
    this.showDeleteModal = false;
    this.mediaToDelete = null;
    this.isDeleting = false;
  }

  /**
   * Confirmer la suppression
   * Supprime le fichier physique ET l'enregistrement en base
   */
  confirmDelete(): void {
    if (!this.mediaToDelete) return;

    this.isDeleting = true;
    this.errorMessage = null;

    this.managerService.deleteIndependentMedia(this.mediaToDelete.id).subscribe({
      next: (response) => {
        this.successMessage = response.message || 'Média supprimé avec succès';
        setTimeout(() => this.successMessage = null, 4000);

        // Retirer le média de la liste des résultats
        if (this.activeTab === 'image') {
          this.generatedImages = this.generatedImages.filter(
            img => img.id !== this.mediaToDelete?.id
          );
        } else {
          if (this.generatedVideo?.id === this.mediaToDelete?.id) {
            this.generatedVideo = null;
          }
        }

        this.closeDeleteModal();

        // Scroll vers le haut pour voir le message
        setTimeout(() => {
          this.scrollToTop();
        }, 100);
      },
      error: (error) => {
        console.error('❌ Erreur suppression:', error);
        this.errorMessage = error.message || 'Erreur lors de la suppression';
        setTimeout(() => this.errorMessage = null, 4000);
        this.isDeleting = false;
      }
    });
  }

  // ============================================================
  // ACTIONS SUR LES MÉDIAS
  // ============================================================

  downloadMedia(media: IndependentMedia): void {
    if (!media || !media.id) return;

    this.managerService.downloadIndependentMedia(media.id).subscribe({
      next: (response) => {
        if (response.download_url) {
          window.open(response.download_url, '_blank');
        }
      },
      error: (error) => {
        this.errorMessage = 'Erreur lors du téléchargement';
        setTimeout(() => this.errorMessage = null, 4000);
      }
    });
  }

  getImageUrl(media: IndependentMedia): string {
    return media.url || '';
  }

  getFileName(media: IndependentMedia): string {
    return media.file_name || 'media';
  }

  getFileSize(media: IndependentMedia): string {
    if (!media.file_size) return '0 KB';
    if (media.file_size < 1024) return `${media.file_size} KB`;
    return `${(media.file_size / 1024).toFixed(1)} MB`;
  }

  resetForm(): void {
    if (this.activeTab === 'image') {
      this.imageForm.reset({
        prompt: '',
        aspect_ratio: '16:9',
        style: 'moderne, professionnel',
        count: 1
      });
      this.generatedImages = [];
    } else {
      this.videoForm.reset({
        prompt: '',
        duration: 8,
        aspect_ratio: '16:9',
        style: 'cinématographique, professionnel'
      });
      this.generatedVideo = null;
    }
    // Recharger les providers après reset
    this.loadActiveProviders(this.activeTab);
    this.errorMessage = null;
    this.successMessage = null;

    // Scroll vers le haut après reset
    setTimeout(() => {
      this.scrollToTop();
    }, 100);
  }

  // ============================================================
  // UTILITAIRES
  // ============================================================

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

  getPromptLength(): number {
    return this.activeTab === 'image'
      ? this.imageForm.get('prompt')?.value?.length || 0
      : this.videoForm.get('prompt')?.value?.length || 0;
  }

  getMaxPromptLength(): number {
    return 1000;
  }

  get minScheduleDate(): string {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 30);
    return now.toISOString().slice(0, 16);
  }
}