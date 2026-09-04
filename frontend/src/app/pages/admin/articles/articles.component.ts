import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AuthService, User } from '../../../core/services/auth.service';
import { AdminService, Article } from '../../../core/services/admin.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-articles',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, SidebarComponent],
  templateUrl: './articles.component.html',
  styleUrls: ['./articles.component.css']
})
export class AdminArticlesComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;

  // Données
  articles: Article[] = [];
  currentPage = 1;
  lastPage = 1;
  totalItems = 0;

  // Filtres
  searchTerm: string = '';
  statusFilter: string = 'all';

  // Modal de suppression
  showDeleteConfirm = false;
  articleToDelete: Article | null = null;
  isSubmitting = false;

  // Modal de visualisation
  showViewModal = false;
  selectedArticle: Article | null = null;

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  constructor(
    private authService: AuthService,
    private adminService: AdminService,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
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

  get draftCount(): number {
    return this.articles.filter(a => a.statut === 'brouillon').length;
  }

  get scheduledCount(): number {
    return this.articles.filter(a => a.statut === 'programme').length;
  }

  get publishedCount(): number {
    return this.articles.filter(a => a.statut === 'publie').length;
  }

  get totalCount(): number {
    return this.articles.length;
  }

  loadArticles(page: number = 1): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.adminService.listAllArticles(page).subscribe({
        next: (response) => {
          this.articles = response.data;
          this.currentPage = response.current_page;
          this.lastPage = response.last_page;
          this.totalItems = response.total;
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

  // Filtrage des articles
  get filteredArticles(): Article[] {
    let filtered = [...this.articles];

    if (this.searchTerm) {
      const term = this.searchTerm.toLowerCase();
      filtered = filtered.filter(article =>
        article.titre.toLowerCase().includes(term) ||
        article.user?.name?.toLowerCase().includes(term) ||
        article.user?.firstname?.toLowerCase().includes(term) ||
        article.site?.url?.toLowerCase().includes(term)
      );
    }

    if (this.statusFilter !== 'all') {
      filtered = filtered.filter(article => article.statut === this.statusFilter);
    }

    return filtered;
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

  // Voir les détails d'un article
  viewArticle(article: Article): void {
    this.selectedArticle = article;
    this.showViewModal = true;
  }

  closeViewModal(): void {
    this.showViewModal = false;
    this.selectedArticle = null;
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
      this.adminService.deleteAnyArticle(this.articleToDelete.id).subscribe({
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

  // Rafraîchir les données
  refreshData(): void {
    this.searchTerm = '';
    this.statusFilter = 'all';
    this.loadArticles(this.currentPage);
  }

  // Helpers
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

  getInitials(name?: string, firstname?: string): string {
    if (firstname && firstname.length > 0 && name && name.length > 0) {
      return (firstname[0] + name[0]).toUpperCase();
    }
    if (name && name.length > 0) return name[0].toUpperCase();
    return '?';
  }

  formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  truncateText(text: string, maxLength: number = 60): string {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
  }

  get greeting(): string {
    if (!this.isBrowser) return 'Bonjour';
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  get firstName(): string {
    return this.currentUser?.firstname ?? this.currentUser?.name ?? 'Administrateur';
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }
}