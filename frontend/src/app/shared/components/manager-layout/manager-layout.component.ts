import { Component, Inject, PLATFORM_ID, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { Router, RouterModule, NavigationEnd } from '@angular/router';
import { Subscription, filter } from 'rxjs';
import { AuthService, User } from '../../../core/services/auth.service';
import { SidebarComponent } from '../sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-manager-layout',
  standalone: true,
  imports: [CommonModule, RouterModule, SidebarComponent],
  template: `
    <div class="app-layout">
      <!-- Sidebar -->

      <!-- Overlay mobile -->
      <div class="mobile-overlay" [class.visible]="mobileSidebarOpen" (click)="toggleSidebar()">
      </div>

      <!-- Main area -->
      <div class="main-area" [class.sidebar-collapsed]="sidebarCollapsed">

        <!-- Topbar commun -->
        <header class="topbar">
          <div class="topbar-left">
            <button class="mobile-menu-btn" (click)="toggleSidebar()">
              <i class="bi bi-list"></i>
            </button>
            <div class="topbar-title">
              <h1 class="page-title">{{ pageTitle }}</h1>
              <span class="page-breadcrumb">{{ pageBreadcrumb }}</span>
            </div>
          </div>
          <div class="topbar-right">
            <button class="refresh-btn" (click)="triggerRefresh()">
              <i class="bi bi-arrow-repeat"></i>
            </button>
          </div>
        </header>

        <!-- ICI le contenu de chaque page change -->
        <main class="layout-content">
          <router-outlet></router-outlet>
        </main>

      </div>
    </div>
  `,
  styleUrls: ['./manager-layout.component.css']
})
export class ManagerLayoutComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  pageTitle = 'Dashboard';
  pageBreadcrumb = 'Gestion de contenu';
  currentUser: User | null = null;
  private isBrowser: boolean;
  private subscriptions = new Subscription();

  constructor(
    @Inject(PLATFORM_ID) private platformId: Object,
    private router: Router,
    private authService: AuthService
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    
    this.subscriptions.add(
      this.router.events.pipe(filter(e => e instanceof NavigationEnd)).subscribe(() => {
        this.updatePageTitle();
      })
    );
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private updatePageTitle(): void {
    const url = this.router.url;
    
    if (url.includes('/manager/dashboard')) {
      this.pageTitle = 'Dashboard';
      this.pageBreadcrumb = 'Aperçu de votre activité';
    } else if (url.includes('/manager/sites')) {
      this.pageTitle = 'Mes sites';
      this.pageBreadcrumb = 'Gestion des sites';
    } else if (url.includes('/manager/redaction')) {
      this.pageTitle = 'Rédaction IA';
      this.pageBreadcrumb = 'Génération d\'articles';
    } else if (url.includes('/manager/articles')) {
      this.pageTitle = 'Mes articles';
      this.pageBreadcrumb = 'Gestion des articles';
    } else if (url.includes('/manager/projets')) {
      this.pageTitle = 'Projets';
      this.pageBreadcrumb = 'Gestion des projets';
    } else if (url.includes('/manager/parametres')) {
      this.pageTitle = 'Paramètres';
      this.pageBreadcrumb = 'Gérez les paramètres de votre compte';
    } else {
      this.pageTitle = 'Dashboard';
      this.pageBreadcrumb = 'Gestion de contenu';
    }
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    
    if (window.innerWidth < 768) {
      this.mobileSidebarOpen = !this.mobileSidebarOpen;
    } else {
      this.sidebarCollapsed = !this.sidebarCollapsed;
    }
  }

  triggerRefresh(): void {
    const outlet = document.querySelector('router-outlet');
    if (outlet && outlet.nextSibling) {
      const component = (outlet.nextSibling as any).__ngContext__;
      if (component && typeof component.refreshData === 'function') {
        component.refreshData();
      }
    }
  }
}