import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { Router, ActivatedRoute, RouterModule } from '@angular/router';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AuthService, User } from '../../../core/services/auth.service';
import {
  ManagerService,
  Site,
  Article,
  GenerateArticlePayload,
  WordPressConfigPayload,
  ArticleMedia,
  MediaImage,
  MediaVideo
} from '../../../core/services/manager.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

interface SuggestedTheme {
  titre: string;
  mot_cle_principal: string;
}

@Component({
  selector: 'app-redaction',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, SidebarComponent],
  templateUrl: './redaction.component.html',
  styleUrls: ['./redaction.component.css']
})
export class ManagerRedactionComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;
  isCancelling = false;
  showEditScheduleModal = false;
  editScheduleDateTime: string = '';
  isUpdatingSchedule = false;
  editScheduleError: string | null = null;
  isEditingSchedule = false;

  // Programmation
  showScheduleModal = false;
  scheduleDateTime: string = '';
  isScheduling = false;
  scheduleError: string | null = null;

  progressValue: number = 0;
  showProgress: boolean = false;
  progressMessage: string = '';

  mediaProgressValue: number = 0;
  showMediaProgress: boolean = false;
  mediaProgressMessage: string = '';

  availableModels: { provider: string; model_name: string; is_active: boolean }[] = [];

  // États
  isLoading = false;
  errorMessage: string | null = null;
  successMessage: string | null = null;

  // Configuration WordPress
  showWpConfigModal = false;
  wpConfigForm: FormGroup;
  isSavingWpConfig = false;
  showWpPassword = false;
  tempSiteIdForConfig: number | null = null;

  // Données
  sites: Site[] = [];
  selectedSiteId: number | null = null;
  selectedSite: Site | null = null;

  // Thèmes suggérés
  suggestedThemes: SuggestedTheme[] = [];

  // Article généré
  article: Article | null = null;
  articleMarkdown: string = '';
  articleTitre: string = '';
  articleMetaDesc: string = '';
  articleSlug: string = '';

  // Formulaires
  generateForm: FormGroup;
  editForm: FormGroup;
  isGenerating = false;
  isUpdating = false;
  isPublishing = false;

  // Options de publication
  categories: string[] = ['Blog', 'Actualités', 'Technologies', 'SEO', 'Marketing', 'Non classé'];
  customTags: string = '';

  // Onglet actif
  activeTab: 'apercu' | 'metadonnees' | 'actions' = 'apercu';

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  // ============================================================
  // Propriétés pour les médias
  // ============================================================

  // Médias
  articleMedia: ArticleMedia[] = [];
  isLoadingMedia = false;
  isGeneratingImages = false;
  isGeneratingVideo = false;

  // Modals médias
  showImageModal = false;
  showVideoModal = false;

  // Formulaires médias
  imageForm: FormGroup;
  videoForm: FormGroup;

  // Images générées
  generatedImages: MediaImage[] = [];
  selectedImageIds: Set<number> = new Set();

  // Vidéo générée
  generatedVideo: MediaVideo | null = null;

  // Upload
  isUploading = false;

  constructor(
    private authService: AuthService,
    private managerService: ManagerService,
    private fb: FormBuilder,
    private router: Router,
    private route: ActivatedRoute,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    this.generateForm = this.createGenerateForm();
    this.editForm = this.createEditForm();
    this.wpConfigForm = this.createWpConfigForm();
    this.imageForm = this.createImageForm();
    this.videoForm = this.createVideoForm();
  }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    if (this.isBrowser) {
      this.loadSites();
      this.loadAvailableModels();
      this.loadArticleMedia();

      this.route.queryParams.subscribe(params => {
        // Vérifier si on doit charger un article existant
        const articleId = params['articleId'];
        if (articleId) {
          this.loadArticle(parseInt(articleId));
        }

        // Sinon, vérifier les autres paramètres pour la génération
        else if (params['siteId']) {
          this.selectedSiteId = parseInt(params['siteId']);
          if (params['sujet']) {
            this.generateForm.patchValue({ sujet: params['sujet'] });
          }
          if (params['motCle']) {
            this.generateForm.patchValue({ mot_cle_principal: params['motCle'] });
          }
        }
      });
    }
  }

  loadAvailableModels(): void {
    this.subscriptions.add(
      this.managerService.getAvailableTextModels().subscribe({
        next: (response) => {
          this.availableModels = response.models;
          // Si un seul modèle disponible, le sélectionner par défaut
          if (this.availableModels.length === 1) {
            const model = this.availableModels[0];
            this.generateForm.patchValue({
              model_id: model.provider + '|' + model.model_name
            });
          }
        },
        error: () => {
          this.availableModels = [];
        }
      })
    );
  }

  getModelLabel(model: { provider: string; model_name: string }): string {
    const providerLabels: { [key: string]: string } = {
      'mistral': 'Mistral AI',
      'openai': 'OpenAI',
      'anthropic': 'Anthropic',
      'deepseek': 'DeepSeek',
      'gemini': 'Google Gemini',
      'google': 'Google',
      'custom': 'Personnalisé'
    };
    const label = providerLabels[model.provider] || model.provider;
    return `${label} - ${model.model_name}`;
  }

  loadArticle(id: number): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.managerService.getArticle(id).subscribe({
        next: (response) => {
          this.article = response;
          this.articleMarkdown = response.contenu_md || '';
          this.articleTitre = response.titre;
          this.articleMetaDesc = response.meta_desc || '';
          this.articleSlug = response.slug || '';

          // Mettre à jour le formulaire d'édition
          this.editForm.patchValue({
            titre: response.titre,
            meta_desc: response.meta_desc || '',
            slug: response.slug || '',
            categorie: response.categorie || '',
            contenu_md: response.contenu_md || ''
          });

          this.customTags = response.tags?.join(', ') || '';

          // Mettre à jour le site sélectionné
          if (response.site_id) {
            this.selectedSiteId = response.site_id;
            this.selectedSite = this.sites.find(s => s.id === response.site_id) || null;
          }

          this.isLoading = false;
          this.successMessage = 'Article chargé avec succès !';
          setTimeout(() => this.successMessage = null, 3000);
        },
        error: (error) => {
          console.error('Erreur chargement article:', error);
          this.errorMessage = error.message || 'Impossible de charger l\'article';
          this.isLoading = false;
          setTimeout(() => this.errorMessage = null, 5000);
        }
      })
    );
  }

  cancelSchedule(): void {
    if (!this.article) return;

    this.isCancelling = true;
    this.errorMessage = null;
    this.successMessage = null;

    this.subscriptions.add(
      this.managerService.cancelSchedule(this.article.id).subscribe({
        next: (response) => {
          if (response.success) {
            this.successMessage = response.message || 'Programmation annulée avec succès';

            // Mettre à jour l'article localement
            if (this.article) {
              this.article.statut = 'brouillon';
              this.article.programme_le = undefined;
            }

            // Recharger l'article pour être sûr
            if (this.article) {
              this.loadArticle(this.article.id);
            }

            setTimeout(() => {
              this.scrollToTop();
            }, 100);
          } else {
            this.errorMessage = response.message || 'Erreur lors de l\'annulation';
          }
          this.isCancelling = false;
          setTimeout(() => {
            if (this.successMessage) this.successMessage = null;
            if (this.errorMessage) this.errorMessage = null;
          }, 4000);
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de l\'annulation';
          this.isCancelling = false;
          setTimeout(() => {
            this.errorMessage = null;
          }, 4000);
        }
      })
    );
  }

  openEditScheduleModal(): void {
    // Pré-remplir avec la date actuelle
    if (this.article?.programme_le) {
      const date = new Date(this.article.programme_le);
      this.editScheduleDateTime = date.toISOString().slice(0, 16);
    } else {
      this.editScheduleDateTime = '';
    }
    this.editScheduleError = null;
    this.showEditScheduleModal = true;
  }

  closeEditScheduleModal(): void {
    this.showEditScheduleModal = false;
    this.editScheduleDateTime = '';
    this.editScheduleError = null;
    this.isUpdatingSchedule = false;
  }

  updateSchedule(): void {
    if (!this.article) return;

    if (!this.editScheduleDateTime) {
      this.editScheduleError = 'Veuillez sélectionner une date et heure';
      return;
    }

    // Vérifier que la date est dans le futur
    const selectedDate = new Date(this.editScheduleDateTime);
    const now = new Date();
    if (selectedDate <= now) {
      this.editScheduleError = 'La date de publication doit être dans le futur. Veuillez sélectionner une date et heure ultérieure à maintenant.';
      return;
    }

    this.isUpdatingSchedule = true;
    this.isEditingSchedule = true;
    this.editScheduleError = null;

    this.subscriptions.add(
      this.managerService.updateSchedule(this.article.id, { scheduled_at: this.editScheduleDateTime }).subscribe({
        next: (response) => {
          this.successMessage = response.message || 'Date de programmation modifiée avec succès';
          this.closeEditScheduleModal();

          if (this.article) {
            this.article.programme_le = response.scheduled_at;
          }

          setTimeout(() => {
            this.scrollToTop();
          }, 100);

          setTimeout(() => this.successMessage = null, 3000);
          this.isUpdatingSchedule = false;
          this.isEditingSchedule = false;
        },
        error: (error) => {
          // Gestion des erreurs de validation 422
          if (error.status === 422 && error.details?.scheduled_at) {
            this.editScheduleError = error.details.scheduled_at[0];
          } else {
            this.editScheduleError = error.message || 'Erreur lors de la modification';
          }
          this.isUpdatingSchedule = false;
          this.isEditingSchedule = false;
          setTimeout(() => this.editScheduleError = null, 4000);
        }
      })
    );
  }

  private createImageForm(): FormGroup {
    return this.fb.group({
      count: [3, [Validators.required, Validators.min(1), Validators.max(10)]],
      aspect_ratio: ['16:9'],
      style: ['moderne, professionnel'],
      custom_prompt: [''],
      provider: ['simulation']
    });
  }

  private createVideoForm(): FormGroup {
    return this.fb.group({
      duration: [8, [Validators.required, Validators.min(5), Validators.max(30)]],
      aspect_ratio: ['16:9'],
      style: ['cinematographique, professionnel'],
      custom_prompt: [''],
      provider: ['simulation']
    });
  }

  // ============================================================
  // GESTION DES MÉDIAS
  // ============================================================

  /**
   * Charger les médias d'un article
   */
  loadArticleMedia(): void {
    if (!this.article) return;

    this.isLoadingMedia = true;
    this.subscriptions.add(
      this.managerService.getArticleMedia(this.article.id).subscribe({
        next: (response) => {
          this.articleMedia = response.media || [];
          console.log('Médias chargés:', this.articleMedia);
          this.isLoadingMedia = false;
        },
        error: (error) => {
          console.error('❌ Erreur chargement médias:', error);
          this.isLoadingMedia = false;
        }
      })
    );
  }

  /**
   * Ouvrir le modal de génération d'images
   */
  openImageModal(): void {
    this.imageForm.reset({
      count: 3,
      aspect_ratio: '16:9',
      style: 'moderne, professionnel',
      custom_prompt: '',
      provider: 'simulation'
    });
    this.generatedImages = [];
    this.selectedImageIds = new Set();
    this.showImageModal = true;
  }

  /**
   * Fermer le modal d'images
   */
  closeImageModal(): void {
    this.showImageModal = false;
    this.generatedImages = [];
    this.selectedImageIds = new Set();
    this.isGeneratingImages = false;
  }

  /**
   * Générer des images
   */
  generateImages(): void {
    if (this.imageForm.invalid || !this.article) return;

    this.isGeneratingImages = true;
    this.errorMessage = null;

    const payload = this.imageForm.value;

    this.subscriptions.add(
      this.managerService.generateImages(this.article.id, payload).subscribe({
        next: (response) => {
          if (response.success) {
            this.generatedImages = response.images || [];
            this.successMessage = `${this.generatedImages.length} images générées avec succès !`;
            console.log('Images générées:', this.generatedImages);

            // Recharger les médias
            this.loadArticleMedia();

            setTimeout(() => this.successMessage = null, 3000);
          } else {
            this.errorMessage = response.message || 'Erreur lors de la génération des images';
            setTimeout(() => this.errorMessage = null, 5000);
          }
          this.isGeneratingImages = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la génération des images';
          setTimeout(() => this.errorMessage = null, 5000);
          this.isGeneratingImages = false;
        }
      })
    );
  }

  /**
   * Ouvrir le modal de génération de vidéo
   */
  openVideoModal(): void {
    this.videoForm.reset({
      duration: 8,
      aspect_ratio: '16:9',
      style: 'cinematographique, professionnel',
      custom_prompt: '',
      provider: 'simulation'
    });
    this.generatedVideo = null;
    this.showVideoModal = true;
  }

  /**
   * Fermer le modal de vidéo
   */
  closeVideoModal(): void {
    this.showVideoModal = false;
    this.generatedVideo = null;
    this.isGeneratingVideo = false;
  }

  /**
   * Générer une vidéo
   */
  generateVideo(): void {
    if (this.videoForm.invalid || !this.article) return;

    this.isGeneratingVideo = true;
    this.errorMessage = null;

    const payload = this.videoForm.value;

    this.subscriptions.add(
      this.managerService.generateVideo(this.article.id, payload).subscribe({
        next: (response) => {
          if (response.success) {
            this.generatedVideo = response.video || null;
            this.successMessage = 'Vidéo générée avec succès !';
            console.log('Vidéo générée:', this.generatedVideo);

            // Recharger les médias
            this.loadArticleMedia();

            setTimeout(() => this.successMessage = null, 3000);
          } else {
            this.errorMessage = response.message || 'Erreur lors de la génération de la vidéo';
            setTimeout(() => this.errorMessage = null, 5000);
          }
          this.isGeneratingVideo = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la génération de la vidéo';
          setTimeout(() => this.errorMessage = null, 5000);
          this.isGeneratingVideo = false;
        }
      })
    );
  }

  /**
   * Sélectionner/désélectionner une image
   */
  toggleImageSelection(imageId: number): void {
    if (this.selectedImageIds.has(imageId)) {
      this.selectedImageIds.delete(imageId);
    } else {
      this.selectedImageIds.add(imageId);
    }
  }

  /**
   * Télécharger un média
   */
  downloadMedia(media: ArticleMedia | MediaImage | MediaVideo): void {
    if (!media || !media.url) return;
    const url = media.url;
    const name = media.file_name || 'media';
    window.open(url, '_blank');
  }

  /**
   * Définir une image comme principale
   */
  setHeroImage(mediaId: number): void {
    if (!this.article) return;

    this.isLoadingMedia = true;
    this.subscriptions.add(
      this.managerService.setHeroImage(this.article.id, mediaId).subscribe({
        next: (response) => {
          if (response.success) {
            this.successMessage = 'Image principale définie avec succès !';
            this.loadArticleMedia();
            setTimeout(() => this.successMessage = null, 3000);
          }
          this.isLoadingMedia = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la définition de l\'image principale';
          setTimeout(() => this.errorMessage = null, 5000);
          this.isLoadingMedia = false;
        }
      })
    );
  }

  /**
   * Intégrer les médias sélectionnés dans l'article
   */
  integrateSelectedMedia(): void {
    if (!this.article || this.selectedImageIds.size === 0) return;

    const mediaIds = Array.from(this.selectedImageIds);

    this.isLoadingMedia = true;
    this.errorMessage = null;
    this.successMessage = null;

    // Démarrer la progression
    this.showMediaProgress = true;
    this.mediaProgressValue = 0;
    this.mediaProgressMessage = 'Préparation de l\'intégration...';

    // Simuler la progression
    const progressInterval = setInterval(() => {
      if (this.mediaProgressValue < 90) {
        this.mediaProgressValue += 15;
        if (this.mediaProgressValue === 30) this.mediaProgressMessage = 'Analyse de l\'article...';
        if (this.mediaProgressValue === 50) this.mediaProgressMessage = 'Positionnement des médias...';
        if (this.mediaProgressValue === 70) this.mediaProgressMessage = 'Génération des descriptions...';
        if (this.mediaProgressValue === 85) this.mediaProgressMessage = 'Finalisation...';
      }
    }, 500);

    this.subscriptions.add(
      this.managerService.integrateMedia(this.article.id, {
        media_ids: mediaIds,
        position: 'inline'
      }).subscribe({
        next: (response) => {
          clearInterval(progressInterval);
          this.mediaProgressValue = 100;
          this.mediaProgressMessage = 'Intégration terminée !';

          setTimeout(() => {
            this.showMediaProgress = false;
            this.mediaProgressValue = 0;
          }, 1000);

          if (response.success) {
            // Mettre à jour l'article avec les médias intégrés
            this.article = response.article;
            this.articleMarkdown = response.article.contenu_md || '';

            // Mettre à jour le formulaire d'édition
            this.editForm.patchValue({
              contenu_md: response.article.contenu_md || ''
            });

            this.successMessage = `${mediaIds.length} média(s) intégré(s) avec succès !`;
            this.loadArticleMedia();
            this.selectedImageIds = new Set();
            this.generatedImages = [];
            this.closeImageModal();

            // Basculer vers l'onglet aperçu pour voir les changements
            this.setActiveTab('apercu');

            setTimeout(() => this.successMessage = null, 5000);
          }
          this.isLoadingMedia = false;
        },
        error: (error) => {
          clearInterval(progressInterval);
          this.showMediaProgress = false;
          this.mediaProgressValue = 0;
          this.errorMessage = error.message || 'Erreur lors de l\'intégration des médias';
          setTimeout(() => this.errorMessage = null, 5000);
          this.isLoadingMedia = false;
        }
      })
    );
  }

  /**
   * Supprimer un média
   */
  deleteMedia(mediaId: number): void {
    if (!this.article) return;

    if (!confirm('Voulez-vous vraiment supprimer ce média ?')) return;

    this.isLoadingMedia = true;
    this.subscriptions.add(
      this.managerService.deleteMedia(this.article.id, mediaId).subscribe({
        next: (response) => {
          if (response.success) {
            this.successMessage = 'Média supprimé avec succès !';
            this.loadArticleMedia();
            setTimeout(() => this.successMessage = null, 3000);
          }
          this.isLoadingMedia = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la suppression du média';
          setTimeout(() => this.errorMessage = null, 5000);
          this.isLoadingMedia = false;
        }
      })
    );
  }

  /**
   * Obtenir l'icône selon le type de média
   */
  getMediaIcon(type: string): string {
    return type === 'image' ? 'bi-image' : 'bi-film';
  }

  /**
   * Obtenir l'URL d'aperçu pour une image
   */
  getImagePreviewUrl(media: ArticleMedia | MediaImage): string {
    return media.url;
  }

  get minScheduleDate(): string {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 30);
    return now.toISOString().slice(0, 16);
  }

  openScheduleModal(): void {
    this.scheduleDateTime = '';
    this.scheduleError = null;
    this.showScheduleModal = true;
  }

  closeScheduleModal(): void {
    this.showScheduleModal = false;
    this.scheduleDateTime = '';
    this.scheduleError = null;
  }

  schedulePublication(): void {
    if (!this.article) return;

    // Validation frontend avant l'envoi
    if (!this.scheduleDateTime) {
      this.scheduleError = 'Veuillez sélectionner une date et heure';
      return;
    }

    // Vérifier que la date est dans le futur
    const selectedDate = new Date(this.scheduleDateTime);
    const now = new Date();
    if (selectedDate <= now) {
      this.scheduleError = 'La date de publication doit être dans le futur. Veuillez sélectionner une date et heure ultérieure à maintenant.';
      return;
    }

    this.isScheduling = true;
    this.scheduleError = null;

    this.subscriptions.add(
      this.managerService.scheduleArticle(this.article.id, { scheduled_at: this.scheduleDateTime }).subscribe({
        next: (response) => {
          this.successMessage = `Article programmé pour le ${new Date(response.scheduled_at).toLocaleString('fr-FR')}`;
          this.closeScheduleModal();

          if (this.article) {
            this.article.statut = 'programme';
            this.article.programme_le = response.scheduled_at;
          }

          setTimeout(() => {
            this.scrollToTop();
          }, 100);

          setTimeout(() => this.successMessage = null, 3000);
          this.isScheduling = false;
        },
        error: (error) => {
          // Gestion des erreurs de validation 422
          if (error.status === 422 && error.details?.scheduled_at) {
            this.scheduleError = error.details.scheduled_at[0];
          } else {
            this.scheduleError = error.message || 'Erreur lors de la programmation';
          }
          setTimeout(() => {
            this.scrollToTop();
          }, 100);
          this.isScheduling = false;
          setTimeout(() => this.scheduleError = null, 5000);
        }
      })
    );
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private createGenerateForm(): FormGroup {
    return this.fb.group({
      site_id: [null, [Validators.required]],
      model_id: ['', [Validators.required]],
      sujet: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(200)]],
      mot_cle_principal: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(100)]],
      longueur_cible: [800, [Validators.required, Validators.min(300), Validators.max(5000)]]
    });
  }

  private createEditForm(): FormGroup {
    return this.fb.group({
      titre: ['', [Validators.required, Validators.maxLength(200)]],
      meta_desc: ['', [Validators.maxLength(160)]],
      slug: ['', []],
      categorie: ['', []],
      contenu_md: ['', []]
    });
  }

  private createWpConfigForm(): FormGroup {
    return this.fb.group({
      wp_url: ['', [Validators.required, Validators.pattern('https?://.+')]],
      wp_username: ['', [Validators.required, Validators.minLength(2)]],
      wp_app_password: ['', [Validators.required, Validators.minLength(10)]],
      test_connection: [true]
    });
  }

  loadSites(): void {
    this.isLoading = true;
    this.subscriptions.add(
      this.managerService.listSites(1).subscribe({
        next: (response) => {
          this.sites = response.data;

          // Si un article est déjà chargé, mettre à jour le site sélectionné
          if (this.article && this.article.site_id) {
            const articleSiteId = this.article.site_id; // Variable temporaire
            this.selectedSiteId = articleSiteId;
            const foundSite = this.sites.find(s => s.id === articleSiteId);
            this.selectedSite = foundSite || null;
            this.onSiteChange();
          }
          // Sinon, sélectionner le premier site par défaut
          else if (this.sites.length > 0 && !this.selectedSiteId) {
            this.selectedSiteId = this.sites[0].id;
            this.onSiteChange();
          }

          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement sites:', error);
          this.errorMessage = error.message || 'Impossible de charger les sites';
          this.isLoading = false;
        }
      })
    );
  }

  onSiteChange(): void {
    if (this.selectedSiteId) {
      // Trouver le site dans la liste
      const found = this.sites.find(s => s.id === this.selectedSiteId);
      this.selectedSite = found || null;

      // RÉINITIALISER les thèmes avant le chargement
      this.suggestedThemes = [];

      // CHARGER les thèmes depuis l'API
      this.managerService.getSiteThemes(this.selectedSiteId).subscribe({
        next: (response) => {
          if (response.has_themes && response.themes.length > 0) {
            this.suggestedThemes = response.themes;
            console.log('Thèmes chargés:', this.suggestedThemes);
          } else {
            this.suggestedThemes = [];
            console.log('⚠️ Aucun thème pour ce site');
          }
        },
        error: (error) => {
          console.error('❌ Erreur chargement thèmes:', error);
          this.suggestedThemes = [];
        }
      });
    } else {
      // Aucun site sélectionné
      this.selectedSite = null;
      this.suggestedThemes = [];
    }
  }

  selectSuggestedTheme(theme: SuggestedTheme): void {
    this.generateForm.patchValue({
      sujet: theme.titre,
      mot_cle_principal: theme.mot_cle_principal
    });
  }

generateArticle(): void {
  if (this.generateForm.invalid) {
    Object.keys(this.generateForm.controls).forEach(key => {
      this.generateForm.get(key)?.markAsTouched();
    });
    return;
  }

  this.isGenerating = true;
  this.errorMessage = null;
  this.successMessage = null;

  // Extraire le provider et le model_name
  const modelId = this.generateForm.value.model_id;
  const [provider, modelName] = modelId.split('|');

  // Démarrer la progression
  this.showProgress = true;
  this.progressValue = 0;
  this.progressMessage = 'Préparation de la génération...';

  const progressInterval = setInterval(() => {
    if (this.progressValue < 90) {
      this.progressValue += 10;
      if (this.progressValue === 30) this.progressMessage = 'Analyse du sujet...';
      if (this.progressValue === 50) this.progressMessage = `Rédaction avec ${this.getModelLabel({ provider, model_name: modelName })}...`;
      if (this.progressValue === 70) this.progressMessage = 'Optimisation SEO...';
      if (this.progressValue === 80) this.progressMessage = 'Finalisation...';
    }
  }, 800);

  const payload: any = {
    site_id: this.generateForm.value.site_id,
    sujet: this.generateForm.value.sujet.trim(),
    mot_cle_principal: this.generateForm.value.mot_cle_principal.trim(),
    longueur_cible: this.generateForm.value.longueur_cible,
    provider: provider,
    model_name: modelName
  };

  // Utiliser generateArticle au lieu de generateArticleWithModel
  this.subscriptions.add(
    this.managerService.generateArticle(payload).subscribe({
      next: (response) => {
        clearInterval(progressInterval);
        this.progressValue = 100;
        this.progressMessage = 'Génération terminée !';

        setTimeout(() => {
          this.showProgress = false;
          this.progressValue = 0;
        }, 1000);

        this.article = response.article;
        this.articleMarkdown = response.article.contenu_md || '';
        this.articleTitre = response.article.titre;
        this.articleMetaDesc = response.article.meta_desc || '';
        this.articleSlug = response.article.slug || '';

        this.editForm.patchValue({
          titre: response.article.titre,
          meta_desc: response.article.meta_desc || '',
          slug: response.article.slug || '',
          categorie: response.article.categorie || '',
          contenu_md: response.article.contenu_md || ''
        });

        this.customTags = response.article.tags?.join(', ') || '';
        this.successMessage = `Article généré avec succès avec ${this.getModelLabel({ provider, model_name: modelName })} !`;
        setTimeout(() => this.successMessage = null, 3000);
        this.isGenerating = false;
      },
      error: (error) => {
        clearInterval(progressInterval);
        this.showProgress = false;
        this.progressValue = 0;
        this.errorMessage = error.message || 'Erreur lors de la génération de l\'article';
        setTimeout(() => this.errorMessage = null, 5000);
        this.isGenerating = false;
      }
    })
  );
}

  applyCorrections(): void {
    if (!this.article) return;

    this.isUpdating = true;
    this.errorMessage = null;
    this.successMessage = null;

    const payload: any = {};
    if (this.editForm.get('titre')?.dirty) payload.titre = this.editForm.value.titre.trim();
    if (this.editForm.get('meta_desc')?.dirty) payload.meta_desc = this.editForm.value.meta_desc?.trim();
    if (this.editForm.get('slug')?.dirty) payload.slug = this.editForm.value.slug?.trim();
    if (this.editForm.get('categorie')?.dirty) payload.categorie = this.editForm.value.categorie?.trim();

    if (this.customTags !== (this.article.tags?.join(', ') || '')) {
      payload.tags = this.customTags.split(',').map(t => t.trim()).filter(t => t);
    }

    if (this.editForm.get('contenu_md')?.dirty) {
      payload.contenu_md = this.editForm.value.contenu_md;
    }

    if (Object.keys(payload).length === 0) {
      this.successMessage = 'Aucune modification détectée';
      setTimeout(() => this.successMessage = null, 2000);
      this.isUpdating = false;
      return;
    }

    this.subscriptions.add(
      this.managerService.updateArticle(this.article.id, payload).subscribe({
        next: (response) => {
          this.article = response.article;
          this.articleMarkdown = response.article.contenu_md || '';
          this.articleTitre = response.article.titre;
          this.articleMetaDesc = response.article.meta_desc || '';
          this.articleSlug = response.article.slug || '';

          this.editForm.patchValue({
            titre: response.article.titre,
            meta_desc: response.article.meta_desc || '',
            slug: response.article.slug || '',
            categorie: response.article.categorie || '',
            contenu_md: response.article.contenu_md || ''
          });

          this.customTags = response.article.tags?.join(', ') || '';
          this.successMessage = 'Corrections appliquées avec succès !';
          setTimeout(() => this.successMessage = null, 3000);
          this.isUpdating = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la mise à jour';
          setTimeout(() => this.errorMessage = null, 5000);
          this.isUpdating = false;
        }
      })
    );
  }

  private scrollToTop(): void {
    const dashboardContent = document.querySelector('.dashboard-content');
    if (dashboardContent) {
      dashboardContent.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }
  }

  publishArticleWithCheck(): void {
    if (!this.article) return;

    this.isPublishing = true;
    this.errorMessage = null;
    this.successMessage = null;

    // Démarrer la progression
    this.showProgress = true;
    this.progressValue = 0;
    this.progressMessage = 'Préparation de la publication...';

    const progressInterval = setInterval(() => {
      if (this.progressValue < 90) {
        this.progressValue += 15;
        if (this.progressValue === 30) this.progressMessage = 'Connexion à WordPress...';
        if (this.progressValue === 50) this.progressMessage = 'Envoi du contenu...';
        if (this.progressValue === 70) this.progressMessage = 'Création des catégories et tags...';
        if (this.progressValue === 85) this.progressMessage = 'Finalisation...';
      }
    }, 500);

    this.subscriptions.add(
      this.managerService.publishArticle(this.article.id).subscribe({
        next: (response) => {
          clearInterval(progressInterval);
          this.progressValue = 100;
          this.progressMessage = 'Publication terminée !';

          setTimeout(() => {
            this.showProgress = false;
            this.progressValue = 0;
          }, 1000);

          if (response.success) {
            this.successMessage = response.message || 'Article publié avec succès sur WordPress !';
            /* if (response.url) {
              setTimeout(() => {
                window.open(response.url, '_blank');
              }, 500);
            } */
            // Rediriger vers le haut après succès
            setTimeout(() => {
              this.scrollToTop();
            }, 100);
            setTimeout(() => {
              this.router.navigate(['/manager/articles']);
            }, 2000);
          } else {
            if (response.requires_config === true && response.site_id) {
              this.tempSiteIdForConfig = response.site_id;
              this.openWpConfigModal();
              this.errorMessage = null;
            } else {
              this.errorMessage = response.message || 'Erreur lors de la publication';
              // Rediriger vers le haut pour voir l'erreur
              setTimeout(() => {
                this.scrollToTop();
              }, 100);
            }
          }
          this.isPublishing = false;
        },
        error: (error) => {
          clearInterval(progressInterval);
          this.showProgress = false;
          this.progressValue = 0;

          if (error.requires_config === true && error.site_id) {
            this.tempSiteIdForConfig = error.site_id;
            this.openWpConfigModal();
            this.errorMessage = null;
          } else {
            this.errorMessage = error.message || 'Erreur lors de la publication';
            // Rediriger vers le haut pour voir l'erreur
            setTimeout(() => {
              this.scrollToTop();
            }, 100);
          }
          this.isPublishing = false;
          setTimeout(() => this.errorMessage = null, 5000);
        }
      })
    );
  }

  openWpConfigModal(): void {
    this.wpConfigForm.reset({
      wp_url: '',
      wp_username: '',
      wp_app_password: '',
      test_connection: true
    });
    this.showWpConfigModal = true;
  }

  closeWpConfigModal(): void {
    this.showWpConfigModal = false;
    this.tempSiteIdForConfig = null;
    this.wpConfigForm.reset();
    this.errorMessage = null;
  }

  saveWpConfigAndPublish(): void {
    if (!this.tempSiteIdForConfig || this.wpConfigForm.invalid) {
      Object.keys(this.wpConfigForm.controls).forEach(key => {
        this.wpConfigForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSavingWpConfig = true;
    this.errorMessage = null;

    const payload: WordPressConfigPayload = {
      wp_url: this.wpConfigForm.value.wp_url,
      wp_username: this.wpConfigForm.value.wp_username,
      wp_app_password: this.wpConfigForm.value.wp_app_password,
      test_connection: this.wpConfigForm.value.test_connection
    };

    this.subscriptions.add(
      this.managerService.saveSiteWpConfig(this.tempSiteIdForConfig, payload).subscribe({
        next: (response) => {
          this.successMessage = 'Configuration enregistrée avec succès !';
          this.closeWpConfigModal();
          this.isSavingWpConfig = false;
          // Réessayer la publication après 1 seconde
          setTimeout(() => {
            this.publishArticleWithCheck();
          }, 1000);
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de l\'enregistrement de la configuration';
          this.isSavingWpConfig = false;
          setTimeout(() => this.errorMessage = null, 4000);
        }
      })
    );
  }

  newArticle(): void {
    this.article = null;
    this.articleMarkdown = '';
    this.articleTitre = '';
    this.articleMetaDesc = '';
    this.articleSlug = '';
    this.customTags = '';
    this.generateForm.reset({
      site_id: this.selectedSiteId,
      model_id: '',
      sujet: '',
      mot_cle_principal: '',
      longueur_cible: 800
    });
    // Si un seul modèle, le sélectionner
    if (this.availableModels.length === 1) {
      const model = this.availableModels[0];
      this.generateForm.patchValue({
        model_id: model.provider + '|' + model.model_name
      });
    }
    this.editForm.reset();
    this.activeTab = 'apercu';
  }

  getStatusLabel(statut: string): string {
    const labels: Record<string, string> = {
      brouillon: 'Brouillon',
      programme: 'Programmé',
      publie: 'Publié'
    };
    return labels[statut] || statut;
  }

  getStatusClass(statut: string): string {
    const classes: Record<string, string> = {
      brouillon: 'status-draft',
      programme: 'status-scheduled',
      publie: 'status-published'
    };
    return classes[statut] || '';
  }

  getWordCount(text: string): number {
    if (!text) return 0;
    return text.split(/\s+/).filter(w => w.length > 0).length;
  }

  getTagsList(): string[] {
    if (this.customTags) {
      return this.customTags.split(',').map(t => t.trim()).filter(t => t).slice(0, 5);
    }
    return this.article?.tags?.slice(0, 5) || [];
  }

  get greeting(): string {
    if (!this.isBrowser) return 'Bonjour';
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  get firstName(): string {
    return this.currentUser?.firstname ?? this.currentUser?.name ?? 'Utilisateur';
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }

  setActiveTab(tab: 'apercu' | 'metadonnees' | 'actions'): void {
    this.activeTab = tab;
  }
}