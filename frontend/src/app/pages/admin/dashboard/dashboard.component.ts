import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AuthService, User } from '../../../core/services/auth.service';
import { AdminService, AdminStats, Manager, Log } from '../../../core/services/admin.service';
import { forkJoin, Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

interface StatCard {
  label: string;
  value: number;
  sub: string;
  icon: string;
  trend?: 'up' | 'down' | null;
  trendValue?: string;
  color: 'green' | 'blue' | 'orange' | 'purple';
}

interface ActivityItem {
  id: number;
  initials: string;
  user: string;
  action: string;
  detail: string;
  time: string;
  type: 'article' | 'site' | 'user' | 'publish' | 'default';
}

interface ManagerRow {
  id: number;
  name: string;
  firstname: string;
  email: string;
  is_active: boolean;
}

@Component({
  selector: 'app-admin-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule, SidebarComponent],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class AdminDashboardComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  user: User | null = null;

  isLoading = true;
  errorMessage: string | null = null;

  stats: StatCard[] = [];
  recentActivity: ActivityItem[] = [];
  managerRows: ManagerRow[] = [];

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
    this.user = this.authService.getUser();
    if (this.isBrowser) {
      this.loadDashboardData();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private loadDashboardData(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      forkJoin({
        stats: this.adminService.getStats(),
        logs: this.adminService.getLogs(1),
        managers: this.adminService.listManagers(1)
      }).subscribe({
        next: ({ stats, logs, managers }) => {
          this.buildStats(stats);
          this.buildActivity(logs.data);
          this.managerRows = managers.data.map(m => ({
            id: m.id,
            name: m.name,
            firstname: m.firstname ?? '',
            email: m.email,
            is_active: m.is_active
          }));
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement dashboard:', error);
          this.errorMessage = error.message || 'Impossible de charger les données du tableau de bord';
          this.isLoading = false;
        }
      })
    );
  }

  private buildStats(s: AdminStats): void {
    this.stats = [
      {
        label: 'Managers actifs',
        value: s.active_managers,
        sub: `${s.inactive_managers} inactif${s.inactive_managers > 1 ? 's' : ''}`,
        icon: 'bi-people-fill',
        trend: s.active_managers > 0 ? 'up' : null,
        trendValue: `${s.total_managers} au total`,
        color: 'green'
      },
      {
        label: 'Articles publiés',
        value: s.articles_publies,
        sub: `${s.articles_brouillon} brouillon${s.articles_brouillon > 1 ? 's' : ''}`,
        icon: 'bi-newspaper',
        trend: s.articles_publies > 0 ? 'up' : null,
        trendValue: `${s.total_articles} au total`,
        color: 'blue'
      },
      {
        label: 'Sites analysés',
        value: s.total_sites,
        sub: 'Sites enregistrés',
        icon: 'bi-globe2',
        trend: s.total_sites > 0 ? 'up' : null,
        trendValue: `+${s.total_sites}`,
        color: 'orange'
      },
      {
        label: 'Articles programmés',
        value: s.articles_programmes,
        sub: 'En attente de publication',
        icon: 'bi-calendar-check-fill',
        trend: null,
        color: 'purple'
      }
    ];
  }

  private buildActivity(logs: Log[]): void {
    this.recentActivity = logs.slice(0, 10).map(log => ({
      id: log.id,
      initials: this.getInitials(log.user),
      user: log.user ? `${log.user.firstname ?? ''} ${log.user.name}`.trim() : `Utilisateur #${log.user_id}`,
      action: this.formatAction(log.action),
      detail: log.details ?? '',
      time: this.formatTime(log.created_at),
      type: this.resolveType(log.action)
    }));
  }

  private getInitials(user?: Manager): string {
    if (!user) return '?';
    const f = user.firstname?.[0] ?? '';
    const n = user.name?.[0] ?? '';
    return (f + n).toUpperCase() || '?';
  }

  private formatAction(action: string): string {
    const map: Record<string, string> = {
      manager_created: 'Manager créé',
      manager_updated: 'Manager mis à jour',
      manager_deleted: 'Manager supprimé',
      manager_status_toggled: 'Statut manager modifié',
      site_analyzed: 'Site analysé',
      site_deleted: 'Site supprimé',
      site_deleted_by_admin: 'Site supprimé (admin)',
      article_generated: 'Article généré',
      article_updated: 'Article mis à jour',
      article_published: 'Article publié',
      article_scheduled: 'Article programmé',
      schedule_cancelled: 'Programmation annulée',
      article_deleted_by_admin: 'Article supprimé (admin)',
    };
    return map[action] ?? action.replace(/_/g, ' ');
  }

  private formatTime(dateStr?: string): string {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diff < 60) return 'À l\'instant';
    if (diff < 3600) return `Il y a ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Il y a ${Math.floor(diff / 3600)} h`;
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
  }

  private resolveType(action: string): ActivityItem['type'] {
    if (action.includes('article') && action.includes('publish')) return 'publish';
    if (action.includes('article')) return 'article';
    if (action.includes('site')) return 'site';
    if (action.includes('manager')) return 'user';
    return 'default';
  }

  refreshData(): void {
    if (this.isBrowser) {
      this.loadDashboardData();
    }
  }

  get greeting(): string {
    if (!this.isBrowser) return 'Bonjour';
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  get firstName(): string {
    return this.user?.firstname ?? this.user?.name ?? 'Administrateur';
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }

  getActivityIcon(type: string): string {
    const map: Record<string, string> = {
      publish: 'bi-rocket-fill',
      site: 'bi-globe2',
      user: 'bi-person-plus-fill',
      article: 'bi-pencil-square',
      default: 'bi-circle'
    };
    return map[type] ?? 'bi-circle';
  }

  getActivityColor(type: string): string {
    const map: Record<string, string> = {
      publish: 'green',
      site: 'blue',
      user: 'purple',
      article: 'orange',
      default: 'gray'
    };
    return map[type] ?? 'gray';
  }
}