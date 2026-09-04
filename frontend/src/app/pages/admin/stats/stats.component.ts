import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AuthService, User } from '../../../core/services/auth.service';
import { AdminService, AdminStats } from '../../../core/services/admin.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-stats',
  standalone: true,
  imports: [CommonModule, RouterModule, SidebarComponent],
  templateUrl: './stats.component.html',
  styleUrls: ['./stats.component.css']
})
export class AdminStatsComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = true;
  errorMessage: string | null = null;

  // Données
  stats: AdminStats | null = null;

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
      this.loadStats();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  loadStats(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.adminService.getStats().subscribe({
        next: (data) => {
          this.stats = data;
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement statistiques:', error);
          this.errorMessage = error.message || 'Impossible de charger les statistiques';
          this.isLoading = false;
        }
      })
    );
  }

  // Rafraîchir les données
  refreshData(): void {
    this.loadStats();
  }

  // Helpers pour les pourcentages
  getManagerActivityRate(): number {
    if (!this.stats || this.stats.total_managers === 0) return 0;
    return Math.round((this.stats.active_managers / this.stats.total_managers) * 100);
  }

  getArticleCompletionRate(): number {
    if (!this.stats || this.stats.total_articles === 0) return 0;
    return Math.round((this.stats.articles_publies / this.stats.total_articles) * 100);
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