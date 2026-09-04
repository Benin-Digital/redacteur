import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AuthService, User } from '../../../core/services/auth.service';
import { AdminService, Log } from '../../../core/services/admin.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-logs',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, SidebarComponent],
  templateUrl: './logs.component.html',
  styleUrls: ['./logs.component.css']
})
export class AdminLogsComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = true;
  errorMessage: string | null = null;

  // Données
  logs: Log[] = [];
  currentPage = 1;
  lastPage = 1;
  totalItems = 0;

  // Filtres
  searchTerm: string = '';
  selectedUserId: number | null = null;
  selectedAction: string = 'all';

  // Liste des actions uniques pour le filtre
  actionsList: string[] = [];

  // Modal de détail
  showDetailModal = false;
  selectedLog: Log | null = null;

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
      this.loadLogs();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  loadLogs(page: number = 1): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.adminService.getLogs(page).subscribe({
        next: (response) => {
          this.logs = response.data;
          this.currentPage = response.current_page;
          this.lastPage = response.last_page;
          this.totalItems = response.total;
          
          // Extraire les actions uniques pour le filtre
          const actions = new Set<string>();
          this.logs.forEach(log => actions.add(log.action));
          this.actionsList = Array.from(actions).sort();
          
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement logs:', error);
          this.errorMessage = error.message || 'Impossible de charger les logs';
          this.isLoading = false;
        }
      })
    );
  }

  // Filtrage des logs
  get filteredLogs(): Log[] {
    let filtered = [...this.logs];

    // Filtre par recherche textuelle
    if (this.searchTerm) {
      const term = this.searchTerm.toLowerCase();
      filtered = filtered.filter(log =>
        log.action?.toLowerCase().includes(term) ||
        log.details?.toLowerCase().includes(term) ||
        log.user?.name?.toLowerCase().includes(term) ||
        log.user?.firstname?.toLowerCase().includes(term) ||
        log.user?.email?.toLowerCase().includes(term) ||
        log.ip_address?.toLowerCase().includes(term)
      );
    }

    // Filtre par utilisateur
    if (this.selectedUserId) {
      filtered = filtered.filter(log => log.user_id === this.selectedUserId);
    }

    // Filtre par action
    if (this.selectedAction !== 'all') {
      filtered = filtered.filter(log => log.action === this.selectedAction);
    }

    return filtered;
  }

  // Pagination
  goToPage(page: number): void {
    if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
      this.loadLogs(page);
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

  // Voir les détails d'un log
  viewLogDetails(log: Log): void {
    this.selectedLog = log;
    this.showDetailModal = true;
  }

  closeDetailModal(): void {
    this.showDetailModal = false;
    this.selectedLog = null;
  }

  // Rafraîchir les données
  refreshData(): void {
    this.searchTerm = '';
    this.selectedUserId = null;
    this.selectedAction = 'all';
    this.loadLogs(this.currentPage);
  }

  // Helpers
  getActionIcon(action: string): string {
    if (action.includes('manager')) return 'bi bi-person-badge';
    if (action.includes('article')) return 'bi bi-file-text';
    if (action.includes('site')) return 'bi bi-globe2';
    if (action.includes('login')) return 'bi bi-box-arrow-in-right';
    if (action.includes('logout')) return 'bi bi-box-arrow-right';
    if (action.includes('delete')) return 'bi bi-trash';
    if (action.includes('create') || action.includes('generated')) return 'bi bi-plus-circle';
    if (action.includes('update') || action.includes('edit')) return 'bi bi-pencil-square';
    if (action.includes('publish')) return 'bi bi-rocket';
    if (action.includes('schedule')) return 'bi bi-calendar-check';
    return 'bi bi-info-circle';
  }

  getActionColor(action: string): string {
    if (action.includes('delete')) return '#dc2626';
    if (action.includes('create') || action.includes('generated')) return '#22c55e';
    if (action.includes('update')) return '#3b82f6';
    if (action.includes('publish')) return '#8b5cf6';
    if (action.includes('schedule')) return '#f59e0b';
    if (action.includes('login')) return '#06b6d4';
    if (action.includes('logout')) return '#6b7280';
    return '#6b7280';
  }

  getActionLabel(action: string): string {
    const labels: Record<string, string> = {
      manager_created: 'Manager créé',
      manager_updated: 'Manager modifié',
      manager_deleted: 'Manager supprimé',
      manager_status_toggled: 'Statut modifié',
      site_analyzed: 'Site analysé',
      site_deleted: 'Site supprimé',
      site_deleted_by_admin: 'Site supprimé (admin)',
      article_generated: 'Article généré',
      article_updated: 'Article modifié',
      article_published: 'Article publié',
      article_scheduled: 'Article programmé',
      schedule_cancelled: 'Programmation annulée',
      article_deleted_by_admin: 'Article supprimé (admin)',
    };
    return labels[action] || action.replace(/_/g, ' ');
  }

  formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  getInitials(name?: string, firstname?: string): string {
    if (firstname && firstname.length > 0 && name && name.length > 0) {
      return (firstname[0] + name[0]).toUpperCase();
    }
    if (name && name.length > 0) return name[0].toUpperCase();
    return '?';
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