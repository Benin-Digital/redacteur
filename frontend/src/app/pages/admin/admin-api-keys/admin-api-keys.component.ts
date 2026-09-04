import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AdminService, SystemApiKey, SystemApiKeyPayload } from '../../../core/services/admin.service';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-admin-api-keys',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, SidebarComponent],
  templateUrl: './admin-api-keys.component.html',
  styleUrls: ['./admin-api-keys.component.css']
})
export class AdminApiKeysComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;

  // États
  isLoading = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  isSubmitting = false;
  isTesting = false;
  showApiKey = false;
  showCustomProvider = false;

  // Données
  apiKeys: SystemApiKey[] = [];
  activeProviders: { provider: string; model_name: string }[] = [];

  // Propriété pour le template (alias vers activeProviders)
  get activeConfig(): { provider: string; model_name: string } | null {
    if (this.activeProviders.length === 0) {
      return null;
    }
    // Retourner le premier provider actif
    return this.activeProviders[0] || null;
  }

  // Pagination
  currentPage = 1;
  lastPage = 1;
  totalItems = 0;
  perPage = 15;

  // Formulaire
  keyForm: FormGroup;
  isEditing = false;
  editingId: number | null = null;

  // Modal
  showModal = false;
  showDeleteConfirm = false;
  keyToDelete: SystemApiKey | null = null;

  // Fournisseurs prédéfinis
  providers = [
    { value: 'pollinations', label: 'Pollinations.ai' },
    { value: 'mistral', label: 'Mistral AI' },
    { value: 'openai', label: 'OpenAI (ChatGPT)' },
    { value: 'anthropic', label: 'Anthropic (Claude)' },
    { value: 'deepseek', label: 'DeepSeek' },
    { value: 'gemini', label: 'Google Gemini' },
    { value: 'google', label: 'Google (Gemini/Imagen/Veo)' },
    { value: 'elevenlabs', label: 'ElevenLabs' },
    { value: 'replicate', label: 'Replicate' },
    { value: 'stability', label: 'Stability AI' },
    { value: 'runway', label: 'Runway' },
    { value: 'pika', label: 'Pika Labs' },
    { value: 'synthesia', label: 'Synthesia' },
    { value: 'custom', label: 'Autre (personnalisé)' }
  ];

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  // Modèles suggérés par fournisseur
  private defaultModels: { [key: string]: string } = {
    'pollinations': 'flux',
    'mistral': 'mistral-large-latest',
    'openai': 'gpt-4o',
    'anthropic': 'claude-3-5-sonnet-20241022',
    'deepseek': 'deepseek-chat',
    'gemini': 'gemini-1.5-pro',
    'google': 'gemini-1.5-pro',
    'elevenlabs': 'eleven_monolingual_v1',
    'replicate': 'replicate/llama-2-70b',
    'stability': 'stable-diffusion-xl-1024-v1-0',
    'runway': 'runwayml/gen-2',
    'pika': 'pika-v1',
    'synthesia': 'synthesia-2.0'
  };

  constructor(
    private adminService: AdminService,
    private fb: FormBuilder,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    this.keyForm = this.createForm();
  }

  ngOnInit(): void {
    if (this.isBrowser) {
      this.loadApiKeys();
      this.loadActiveConfig();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private createForm(): FormGroup {
    return this.fb.group({
      provider: ['openai', Validators.required],
      custom_provider_name: [''],
      api_key: ['', [Validators.required, Validators.minLength(10)]],
      model_name: [''],
      capabilities: this.fb.group({
        text: [true],
        image: [false],
        video: [false],
        audio: [false]
      }),
      is_active: [true]
    });
  }

  // ============================================================
  // CHARGEMENT DES DONNÉES
  // ============================================================

  loadApiKeys(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.adminService.listApiKeys(this.currentPage).subscribe({
        next: (response) => {
          this.apiKeys = response.data;
          this.currentPage = response.current_page;
          this.lastPage = response.last_page;
          this.totalItems = response.total;
          this.perPage = response.per_page;
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement clés API:', error);
          this.errorMessage = error.message || 'Impossible de charger les clés API';
          this.isLoading = false;
        }
      })
    );
  }

  loadActiveConfig(): void {
    this.subscriptions.add(
      this.adminService.getActiveConfig().subscribe({
        next: (config) => {
          // La réponse est un tableau ou un objet
          if (Array.isArray(config)) {
            this.activeProviders = config.map(c => ({
              provider: c.provider,
              model_name: c.model_name || 'Modèle par défaut'
            }));
          } else if (config && typeof config === 'object' && config.provider) {
            // Si c'est un seul objet
            this.activeProviders = [{
              provider: config.provider,
              model_name: config.model_name || 'Modèle par défaut'
            }];
          } else {
            this.activeProviders = [];
          }
        },
        error: () => {
          this.activeProviders = [];
        }
      })
    );
  }

  refreshData(): void {
    this.loadApiKeys();
    this.loadActiveConfig();
  }

  // ============================================================
  // GESTION DU FORMULAIRE
  // ============================================================

  onProviderChange(): void {
    const provider = this.keyForm.get('provider')?.value;

    // Afficher le champ personnalisé si "custom" est sélectionné
    this.showCustomProvider = provider === 'custom';

    if (provider === 'custom') {
      this.keyForm.get('custom_provider_name')?.setValidators([Validators.required, Validators.minLength(2)]);
      this.keyForm.get('custom_provider_name')?.updateValueAndValidity();
    } else {
      this.keyForm.get('custom_provider_name')?.clearValidators();
      this.keyForm.get('custom_provider_name')?.setValue('');
      this.keyForm.get('custom_provider_name')?.updateValueAndValidity();
    }

    // Suggérer un modèle par défaut
    const defaultModel = this.defaultModels[provider];
    if (defaultModel && !this.isEditing) {
      this.keyForm.get('model_name')?.setValue(defaultModel);
    }

    // Définir les capacités par défaut selon le fournisseur
    this.setDefaultCapabilities(provider);
  }

  private setDefaultCapabilities(provider: string): void {
    const caps = this.keyForm.get('capabilities') as FormGroup;

    // Réinitialiser
    caps.patchValue({
      text: false,
      image: false,
      video: false,
      audio: false
    });

    // Définir par défaut selon le fournisseur
    switch (provider) {
      case 'pollinations': // AJOUTER
        caps.patchValue({ image: true, video: true });
        break;
      case 'openai':
      case 'mistral':
      case 'anthropic':
      case 'deepseek':
      case 'gemini':
      case 'google':
        caps.patchValue({ text: true });
        break;
      case 'stability':
        caps.patchValue({ image: true });
        break;
      case 'runway':
      case 'pika':
        caps.patchValue({ video: true });
        break;
      case 'elevenlabs':
        caps.patchValue({ audio: true });
        break;
      case 'replicate':
        caps.patchValue({ text: true, image: true, video: true });
        break;
    }
  }

  // ============================================================
  // OUVRIR / FERMER LE MODAL
  // ============================================================

  openCreateModal(): void {
    this.isEditing = false;
    this.editingId = null;
    this.showCustomProvider = false;
    this.errorMessage = null;
    this.keyForm.reset({
      provider: 'openai',
      custom_provider_name: '',
      api_key: '',
      model_name: 'gpt-4o',
      capabilities: {
        text: true,
        image: false,
        video: false,
        audio: false
      },
      is_active: true
    });
    this.onProviderChange();
    this.showModal = true;
  }

  openEditModal(key: SystemApiKey): void {
    this.isEditing = true;
    this.editingId = key.id;
    this.errorMessage = null;
    this.showCustomProvider = false;

    // Déterminer si c'est un provider personnalisé (non listé)
    const isCustom = !this.providers.some(p => p.value === key.provider);

    const capabilities = key.capabilities || ['text_generation'];

    this.keyForm.patchValue({
      provider: isCustom ? 'custom' : key.provider,
      custom_provider_name: isCustom ? key.provider : '',
      api_key: '',
      model_name: key.model_name || '',
      capabilities: {
        text: capabilities.includes('text_generation'),
        image: capabilities.includes('image_generation'),
        video: capabilities.includes('video_generation'),
        audio: capabilities.includes('audio_generation')
      },
      is_active: key.is_active
    });

    if (isCustom) {
      this.showCustomProvider = true;
      this.keyForm.get('custom_provider_name')?.setValidators([Validators.required, Validators.minLength(2)]);
      this.keyForm.get('custom_provider_name')?.updateValueAndValidity();
    }

    // En édition, la clé n'est pas requise
    this.keyForm.get('api_key')?.clearValidators();
    this.keyForm.get('api_key')?.setValidators([Validators.minLength(10)]);
    this.keyForm.get('api_key')?.updateValueAndValidity();

    this.showModal = true;
  }

  closeModal(): void {
    this.showModal = false;
    this.errorMessage = null;
    // Remettre les validateurs par défaut
    this.keyForm.get('api_key')?.setValidators([Validators.required, Validators.minLength(10)]);
    this.keyForm.get('api_key')?.updateValueAndValidity();
  }

  // ============================================================
  // SOUMISSION DU FORMULAIRE
  // ============================================================

  onSubmit(): void {
    if (this.keyForm.invalid) {
      Object.keys(this.keyForm.controls).forEach(key => {
        this.keyForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSubmitting = true;
    this.errorMessage = null;

    const formValue = this.keyForm.value;

    // Déterminer le provider final
    let provider = formValue.provider;
    if (provider === 'custom') {
      provider = formValue.custom_provider_name?.trim().toLowerCase();
      if (!provider) {
        this.errorMessage = 'Veuillez entrer un nom de fournisseur valide';
        this.isSubmitting = false;
        return;
      }
    }

    // Construire les capacités
    const capabilities: string[] = [];
    if (formValue.capabilities.text) capabilities.push('text_generation');
    if (formValue.capabilities.image) capabilities.push('image_generation');
    if (formValue.capabilities.video) capabilities.push('video_generation');
    if (formValue.capabilities.audio) capabilities.push('audio_generation');

    // Vérifier qu'au moins une capacité est sélectionnée
    if (capabilities.length === 0) {
      this.errorMessage = 'Veuillez sélectionner au moins une capacité';
      this.isSubmitting = false;
      return;
    }

    const payload: SystemApiKeyPayload = {
      provider: provider,
      api_key: formValue.api_key || '',
      model_name: formValue.model_name || null,
      capabilities: capabilities,
      is_active: formValue.is_active,
      priority: 0 // Non utilisé, on met 0 par défaut
    };

    if (this.isEditing && this.editingId) {
      // Si la clé est vide en édition, on ne l'envoie pas
      if (!payload.api_key) {
        delete (payload as any).api_key;
      }

      this.subscriptions.add(
        this.adminService.updateApiKey(this.editingId, payload).subscribe({
          next: (response) => {
            this.successMessage = response.message;
            this.closeModal();
            this.loadApiKeys();
            this.loadActiveConfig();
            setTimeout(() => this.successMessage = null, 4000);
            this.isSubmitting = false;
          },
          error: (error) => {
            this.errorMessage = error.message || 'Erreur lors de la mise à jour';
            this.isSubmitting = false;
          }
        })
      );
    } else {
      this.subscriptions.add(
        this.adminService.createApiKey(payload).subscribe({
          next: (response) => {
            this.successMessage = response.message;
            this.closeModal();
            this.loadApiKeys();
            this.loadActiveConfig();
            setTimeout(() => this.successMessage = null, 4000);
            this.isSubmitting = false;
          },
          error: (error) => {
            this.errorMessage = error.message || 'Erreur lors de la création';
            this.isSubmitting = false;
          }
        })
      );
    }
  }

  // ============================================================
  // TEST DE CONNEXION
  // ============================================================

  testApiKey(key: SystemApiKey): void {
    this.isTesting = true;
    this.errorMessage = null;
    this.successMessage = null;

    this.subscriptions.add(
      this.adminService.testApiKey(key.id).subscribe({
        next: (result) => {
          if (result.success) {
            this.successMessage = `${result.message}`;
          } else {
            this.errorMessage = `❌ ${result.message}`;
          }
          this.isTesting = false;
          setTimeout(() => {
            this.successMessage = null;
            this.errorMessage = null;
          }, 5000);
        },
        error: (error) => {
          this.errorMessage = `❌ Erreur lors du test : ${error.message}`;
          this.isTesting = false;
          setTimeout(() => this.errorMessage = null, 5000);
        }
      })
    );
  }

  // ============================================================
  // SUPPRESSION
  // ============================================================

  confirmDelete(key: SystemApiKey): void {
    this.keyToDelete = key;
    this.showDeleteConfirm = true;
  }

  cancelDelete(): void {
    this.keyToDelete = null;
    this.showDeleteConfirm = false;
  }

  deleteApiKey(): void {
    if (!this.keyToDelete) return;

    this.isSubmitting = true;

    this.subscriptions.add(
      this.adminService.deleteApiKey(this.keyToDelete.id).subscribe({
        next: (response) => {
          this.successMessage = response.message;
          this.showDeleteConfirm = false;
          this.keyToDelete = null;
          this.loadApiKeys();
          this.loadActiveConfig();
          setTimeout(() => this.successMessage = null, 3000);
          this.isSubmitting = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la suppression';
          this.isSubmitting = false;
          setTimeout(() => this.errorMessage = null, 3000);
        }
      })
    );
  }

  // ============================================================
  // PAGINATION
  // ============================================================

  goToPage(page: number): void {
    if (page < 1 || page > this.lastPage || page === this.currentPage) return;
    this.currentPage = page;
    this.loadApiKeys();
  }

  get pages(): number[] {
    const pages: number[] = [];
    const total = this.lastPage;
    const current = this.currentPage;

    if (total <= 7) {
      for (let i = 1; i <= total; i++) {
        pages.push(i);
      }
    } else {
      pages.push(1);
      if (current > 3) pages.push(-1);

      const start = Math.max(2, current - 1);
      const end = Math.min(total - 1, current + 1);

      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      if (current < total - 2) pages.push(-1);
      pages.push(total);
    }

    return pages;
  }

  // ============================================================
  // UTILITAIRES
  // ============================================================

  getProviderLabel(provider: string): string {
    const found = this.providers.find(p => p.value === provider);
    return found ? found.label : provider.charAt(0).toUpperCase() + provider.slice(1);
  }

  getProviderIcon(provider: string): string {
    const icons: { [key: string]: string } = {
      'pollinations': 'bi-flower1',
      'mistral': 'bi-cloud-snow',
      'openai': 'bi-stars',
      'anthropic': 'bi-shield-check',
      'deepseek': 'bi-search',
      'gemini': 'bi-infinity',
      'google': 'bi-google',
      'elevenlabs': 'bi-volume-up-fill',
      'replicate': 'bi-diagram-3',
      'stability': 'bi-image',
      'runway': 'bi-film',
      'pika': 'bi-play-circle',
      'synthesia': 'bi-person-video',
      'custom': 'bi-gear'
    };
    return icons[provider] || 'bi-box';
  }

  formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  // ============================================================
  // SIDEBAR
  // ============================================================

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }

  get greeting(): string {
    if (!this.isBrowser) return 'Bonjour';
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }
}