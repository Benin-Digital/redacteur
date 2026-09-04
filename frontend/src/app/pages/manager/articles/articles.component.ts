import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AuthService, User } from '../../../core/services/auth.service';
import { ManagerService, Article, UpdateArticlePayload } from '../../../core/services/manager.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

interface StatCard {
  label: string;
  value: number;
  sub: string;
  icon: string;
  color: 'green' | 'blue' | 'orange' | 'purple' | 'teal';
}

@Component({
  selector: 'app-articles',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, SidebarComponent],
  templateUrl: './articles.component.html',
  styleUrls: ['./articles.component.css']
})
export class ManagerArticlesComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;
  publishedUrl: string | null = null;

  // États
  isLoading = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;

  // Données
  articles: Article[] = [];
  currentPage = 1;
  lastPage = 1;
  totalItems = 0;
  perPage = 15;

  // Statistiques
  stats: StatCard[] = [];

  // Modal d'édition
  showEditModal = false;
  editForm: FormGroup;
  isSubmitting = false;
  currentArticle: Article | null = null;

  // Modal de suppression
  showDeleteConfirm = false;
  articleToDelete: Article | null = null;

  // Filtres
  searchTerm = '';
  statusFilter = '';

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  constructor(
    private authService: AuthService,
    private managerService: ManagerService,
    private fb: FormBuilder,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    this.editForm = this.createEditForm();
  }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    if (this.isBrowser) {
      this.loadArticles();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private createEditForm(): FormGroup {
    return this.fb.group({
      titre: ['', [Validators.required, Validators.maxLength(200)]],
      meta_desc: ['', [Validators.maxLength(160)]],
      slug: [''],
      categorie: [''],
      contenu_md: ['']
    });
  }

  loadArticles(page: number = 1): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.managerService.listArticles(page).subscribe({
        next: (response) => {
          this.articles = response.data;
          this.currentPage = response.current_page;
          this.lastPage = response.last_page;
          this.totalItems = response.total;
          this.updateStats();
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement articles:', error);
          this.errorMessage = error.message || 'Impossible de charger la liste des articles';
          this.isLoading = false;
        }
      })
    );
  }

  private updateStats(): void {
    const total = this.articles.length;
    const brouillons = this.articles.filter(a => a.statut === 'brouillon').length;
    const programmes = this.articles.filter(a => a.statut === 'programme').length;
    const publies = this.articles.filter(a => a.statut === 'publie').length;
    const totalMots = this.articles.reduce((sum, a) => sum + this.getWordCount(a.contenu_md || ''), 0);

    this.stats = [
      {
        label: 'Total articles',
        value: total,
        sub: 'Articles créés',
        icon: 'bi-file-text-fill',
        color: 'blue'
      },
      {
        label: 'Brouillons',
        value: brouillons,
        sub: 'En cours de rédaction',
        icon: 'bi-pencil',
        color: 'orange'
      },
      {
        label: 'Programmés',
        value: programmes,
        sub: 'Publication planifiée',
        icon: 'bi-calendar-check',
        color: 'purple'
      },
      {
        label: 'Publiés',
        value: publies,
        sub: 'Sur WordPress',
        icon: 'bi-check-circle-fill',
        color: 'green'
      },
      {
        label: 'Total mots',
        value: totalMots,
        sub: 'Mots rédigés',
        icon: 'bi-text-paragraph',
        color: 'teal'
      }
    ];
  }

  // Pagination
  goToPage(page: number): void {
    if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
      this.loadArticles(page);
    }
  }

  get pages(): number[] {
    const pages: number[] = [];
    const start = Math.max(1, this.currentPage - 2);
    const end = Math.min(this.lastPage, start + 4);
    for (let i = start; i <= end; i++) {
      pages.push(i);
    }
    return pages;
  }

  // Filtrer les articles (frontend)
  get filteredArticles(): Article[] {
    let filtered = [...this.articles];

    if (this.searchTerm) {
      const term = this.searchTerm.toLowerCase();
      filtered = filtered.filter(a =>
        a.titre.toLowerCase().includes(term) ||
        (a.meta_desc && a.meta_desc.toLowerCase().includes(term)) ||
        (a.site?.url && a.site.url.toLowerCase().includes(term))
      );
    }

    if (this.statusFilter) {
      filtered = filtered.filter(a => a.statut === this.statusFilter);
    }

    return filtered;
  }

  // Modal d'édition
  openEditModal(article: Article): void {
    this.currentArticle = article;
    this.editForm.patchValue({
      titre: article.titre,
      meta_desc: article.meta_desc || '',
      slug: article.slug || '',
      categorie: article.categorie || '',
      contenu_md: article.contenu_md || ''
    });
    this.showEditModal = true;
  }

  closeEditModal(): void {
    this.showEditModal = false;
    this.currentArticle = null;
    this.editForm.reset();
    this.errorMessage = null;
  }

  onSubmitEdit(): void {
    if (!this.currentArticle) return;

    if (this.editForm.invalid) {
      Object.keys(this.editForm.controls).forEach(key => {
        this.editForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSubmitting = true;
    this.errorMessage = null;

    const payload: UpdateArticlePayload = {
      titre: this.editForm.value.titre.trim(),
      meta_desc: this.editForm.value.meta_desc?.trim(),
      slug: this.editForm.value.slug?.trim(),
      categorie: this.editForm.value.categorie?.trim(),
      contenu_md: this.editForm.value.contenu_md
    };

    this.subscriptions.add(
      this.managerService.updateArticle(this.currentArticle.id, payload).subscribe({
        next: (response) => {
          this.closeEditModal();
          this.loadArticles(this.currentPage);
          this.successMessage = 'Article modifié avec succès';
          setTimeout(() => {
            this.successMessage = null;
          }, 3000);
          this.isSubmitting = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la modification';
          this.isSubmitting = false;
          setTimeout(() => {
            this.errorMessage = null;
          }, 4000);
        }
      })
    );
  }

  // Confirmation suppression
  confirmDelete(article: Article): void {
    this.articleToDelete = article;
    this.showDeleteConfirm = true;
  }

  cancelDelete(): void {
    this.showDeleteConfirm = false;
    this.articleToDelete = null;
  }

  deleteArticle(): void {
    if (!this.articleToDelete) return;

    this.isSubmitting = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.managerService.deleteArticle(this.articleToDelete.id).subscribe({
        next: () => {
          this.showDeleteConfirm = false;
          this.articleToDelete = null;
          this.loadArticles(this.currentPage);
          this.isSubmitting = false;
          this.successMessage = 'Article supprimé avec succès';
          setTimeout(() => {
            this.successMessage = null;
          }, 3000);
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la suppression';
          this.isSubmitting = false;
          setTimeout(() => {
            this.errorMessage = null;
          }, 3000);
        }
      })
    );
  }

  // Publier depuis la liste
  publishArticle(article: Article): void {
    if (article.statut === 'publie') {
      this.errorMessage = 'Cet article est déjà publié';
      setTimeout(() => {
        this.errorMessage = null;
      }, 3000);
      return;
    }

    this.isSubmitting = true;

    this.subscriptions.add(
      this.managerService.publishArticle(article.id).subscribe({
        next: (response) => {
          if (response.success) {
            // Stocker l'URL publiée
            this.publishedUrl = response.url || response.article_url || null;

            // Message avec lien si disponible
            if (this.publishedUrl) {
              this.successMessage = `${response.message || 'Article publié avec succès !'} <a href="${this.publishedUrl}" target="_blank" rel="noopener noreferrer">Voir l'article</a>`;
            } else {
              this.successMessage = response.message || 'Article publié avec succès !';
            }

            this.loadArticles(this.currentPage);
          } else {
            this.errorMessage = response.message || 'Erreur lors de la publication';
          }
          this.isSubmitting = false;
          setTimeout(() => {
            this.successMessage = null;
            this.errorMessage = null;
            this.publishedUrl = null;
          }, 8000);
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la publication';
          this.isSubmitting = false;
          setTimeout(() => {
            this.errorMessage = null;
          }, 4000);
        }
      })
    );
  }

  // Rediriger vers la rédaction pour modifier
  editInRedaction(article: Article): void {
    this.router.navigate(['/manager/redaction'], {
      queryParams: { articleId: article.id }
    });
  }

  // Voir l'aperçu
  viewArticle(article: Article): void {
    this.router.navigate(['/manager/redaction'], {
      queryParams: {
        articleId: article.id,
        view: 'preview',
        title: article.titre
      }
    });
  }

  openPreview(article: Article): void {
    if (article.statut === 'publie' && article.wp_url) {
      window.open(article.wp_url, '_blank');
    } else if (article.statut === 'publie' && !article.wp_url) {
      this.errorMessage = 'URL de l\'article non disponible';
      setTimeout(() => {
        this.errorMessage = null;
      }, 3000);
    }
  }

  // Rafraîchir les données
  refreshData(): void {
    this.loadArticles(this.currentPage);
  }

  // Helpers
  formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
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

  clearFilters(): void {
    this.searchTerm = '';
    this.statusFilter = '';
  }
}