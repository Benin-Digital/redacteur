import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AuthService, User } from '../../../core/services/auth.service';
import { ManagerService, Site, Article } from '../../../core/services/manager.service';
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

interface RecentArticle {
  id: number;
  titre: string;
  statut: string;
  site_url: string;
  created_at: string;
}

@Component({
  selector: 'app-manager-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule, SidebarComponent],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class ManagerDashboardComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  user: User | null = null;

  isLoading = true;
  errorMessage: string | null = null;

  stats: StatCard[] = [];
  recentArticles: RecentArticle[] = [];
  recentSites: Site[] = [];

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  constructor(
    private authService: AuthService,
    private managerService: ManagerService,
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

  refreshData(): void {
    if (this.isBrowser) {
      this.loadDashboardData();
    }
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }

  private loadDashboardData(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      forkJoin({
        sites: this.managerService.listSites(1),
        articles: this.managerService.listArticles(1)
      }).subscribe({
        next: ({ sites, articles }) => {
          this.recentSites = sites.data.slice(0, 5);
          
          this.recentArticles = articles.data.slice(0, 5).map(article => ({
            id: article.id,
            titre: article.titre,
            statut: article.statut,
            site_url: article.site?.url || 'Site inconnu',
            created_at: article.created_at || ''
          }));
          
          this.updateStats();
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement dashboard:', error);
          this.errorMessage = error.message || 'Impossible de charger les données';
          this.isLoading = false;
        }
      })
    );
  }

  private updateStats(): void {
    this.stats = [
      {
        label: 'Mes sites',
        value: this.recentSites.length,
        sub: 'Sites analysés',
        icon: 'bi-globe2',
        trend: null,
        color: 'green'
      },
      {
        label: 'Mes articles',
        value: this.recentArticles.length,
        sub: 'Articles créés',
        icon: 'bi-file-text-fill',
        trend: null,
        color: 'blue'
      },
      {
        label: 'Articles publiés',
        value: this.recentArticles.filter(a => a.statut === 'publie').length,
        sub: 'Publiés sur WordPress',
        icon: 'bi-check-circle-fill',
        trend: null,
        color: 'orange'
      },
      {
        label: 'En attente',
        value: this.recentArticles.filter(a => a.statut === 'brouillon' || a.statut === 'programme').length,
        sub: 'Brouillons et programmés',
        icon: 'bi-clock-history',
        trend: null,
        color: 'purple'
      }
    ];
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

  formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  get greeting(): string {
    if (!this.isBrowser) return 'Bonjour';
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  get firstName(): string {
    return this.user?.firstname ?? this.user?.name ?? 'Utilisateur';
  }
}