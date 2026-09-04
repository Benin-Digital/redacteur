import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ManagerService, Site, SuggestedTheme, AnalyzeSitePayload } from '../../../core/services/manager.service';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-manager-sites',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, SidebarComponent],
  templateUrl: './sites.component.html',
  styleUrls: ['./sites.component.css']
})
export class ManagerSitesComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;

  // États
  isLoading = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  isSubmitting = false;

  // Données
  sites: Site[] = [];
  analysisResult: any = null;
  suggestedThemes: SuggestedTheme[] = [];

  // Modèles disponibles
  availableModels: { provider: string; model_name: string; is_active: boolean }[] = [];

  // Pagination
  currentPage = 1;
  lastPage = 1;
  totalItems = 0;
  perPage = 10;

  // Formulaire
  siteForm: FormGroup;

  // Modals
  showAddModal = false;
  showAnalysisModal = false;
  showDeleteConfirm = false;
  siteToDelete: Site | null = null;

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  constructor(
    private managerService: ManagerService,
    private fb: FormBuilder,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    this.siteForm = this.createForm();
  }

  ngOnInit(): void {
    if (this.isBrowser) {
      this.loadSites();
      this.loadAvailableModels();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private createForm(): FormGroup {
    return this.fb.group({
      url: ['', [Validators.required, Validators.pattern('https?://.+')]],
      description: [''],
      model_id: ['', Validators.required]
    });
  }

  // ============================================================
  // CHARGEMENT DES DONNÉES
  // ============================================================

  loadSites(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.managerService.listSites(this.currentPage).subscribe({
        next: (response) => {
          this.sites = response.data;
          this.currentPage = response.current_page;
          this.lastPage = response.last_page;
          this.totalItems = response.total;
          this.perPage = response.per_page;
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement sites:', error);
          this.errorMessage = error.message || 'Impossible de charger la liste des sites';
          this.isLoading = false;
        }
      })
    );
  }

  loadAvailableModels(): void {
    this.subscriptions.add(
      this.managerService.getAvailableTextModels().subscribe({
        next: (response) => {
          this.availableModels = response.models;
          // Si un seul modèle disponible, le sélectionner par défaut
          if (this.availableModels.length === 1) {
            const model = this.availableModels[0];
            this.siteForm.patchValue({
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

  // ============================================================
  // GESTION DU FORMULAIRE
  // ============================================================

  openAddModal(): void {
    this.errorMessage = null;
    this.siteForm.reset({
      url: '',
      description: '',
      model_id: ''
    });
    // Si un seul modèle disponible, le sélectionner
    if (this.availableModels.length === 1) {
      const model = this.availableModels[0];
      this.siteForm.patchValue({
        model_id: model.provider + '|' + model.model_name
      });
    }
    this.showAddModal = true;
  }

  closeAddModal(): void {
    this.showAddModal = false;
    this.errorMessage = null;
  }

onSubmit(): void {
  if (this.siteForm.invalid) {
    Object.keys(this.siteForm.controls).forEach(key => {
      this.siteForm.get(key)?.markAsTouched();
    });
    return;
  }

  this.isSubmitting = true;
  this.errorMessage = null;

  const formValue = this.siteForm.value;
  
  // Extraire le provider et le model_name du model_id
  const modelId = formValue.model_id;
  if (!modelId) {
    this.errorMessage = 'Veuillez sélectionner un modèle';
    this.isSubmitting = false;
    return;
  }
  
  const parts = modelId.split('|');
  if (parts.length !== 2) {
    this.errorMessage = 'Format de modèle invalide';
    this.isSubmitting = false;
    return;
  }
  
  const [provider, modelName] = parts;

  const payload = {
    url: formValue.url.trim(),
    description: formValue.description?.trim() || undefined,
    provider: provider,
    model_name: modelName
  };

  this.subscriptions.add(
    this.managerService.analyzeSite(payload).subscribe({
      next: (response) => {
        this.successMessage = response.message;
        this.closeAddModal();
        this.loadSites();
        setTimeout(() => {
          this.successMessage = null;
        }, 4000);
        this.isSubmitting = false;
      },
      error: (error) => {
        console.error('Erreur analyse site:', error);
        this.errorMessage = error.message || 'Erreur lors de l\'analyse du site';
        this.isSubmitting = false;
        setTimeout(() => {
          this.errorMessage = null;
        }, 5000);
      },
      complete: () => {
        this.isSubmitting = false;
      }
    })
  );
}

  // ============================================================
  // ANALYSE
  // ============================================================

  viewAnalysis(site: Site): void {
    this.analysisResult = site.contexte_json;
    this.suggestedThemes = site.contexte_json?.themes_suggeres_pour_articles || [];
    this.showAnalysisModal = true;
  }

  closeAnalysisModal(): void {
    this.showAnalysisModal = false;
    this.analysisResult = null;
    this.suggestedThemes = [];
  }

  selectTheme(theme: SuggestedTheme): void {
    this.closeAnalysisModal();
    this.router.navigate(['/manager/redaction'], {
      queryParams: {
        siteId: this.analysisResult?.site_id,
        sujet: theme.titre,
        motCle: theme.mot_cle_principal
      }
    });
  }

  // ============================================================
  // SUPPRESSION
  // ============================================================

  confirmDelete(site: Site): void {
    this.siteToDelete = site;
    this.showDeleteConfirm = true;
  }

  cancelDelete(): void {
    this.siteToDelete = null;
    this.showDeleteConfirm = false;
  }

  deleteSite(): void {
    if (!this.siteToDelete) return;

    this.isSubmitting = true;

    this.subscriptions.add(
      this.managerService.deleteSite(this.siteToDelete.id).subscribe({
        next: (response) => {
          this.successMessage = response.message;
          this.showDeleteConfirm = false;
          this.siteToDelete = null;
          this.loadSites();
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
  // UTILITAIRES
  // ============================================================

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

  refreshData(): void {
    this.loadSites();
    this.loadAvailableModels();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.lastPage || page === this.currentPage) return;
    this.currentPage = page;
    this.loadSites();
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
}