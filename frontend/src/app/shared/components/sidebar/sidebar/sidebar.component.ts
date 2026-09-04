// sidebar.component.ts - Version mise à jour

import { Component, OnInit, OnDestroy, Output, EventEmitter, Input, Inject, PLATFORM_ID } from '@angular/core';
import { Router, RouterModule, NavigationEnd } from '@angular/router';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { Subscription, filter } from 'rxjs';
import { AuthService, User } from '../../../../core/services/auth.service';

interface NavItem {
  label: string;
  icon: string;
  route: string;
  badge?: string | null;
}

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.css']
})
export class SidebarComponent implements OnInit, OnDestroy {
  @Input() collapsed = false;
  @Output() collapsedChange = new EventEmitter<boolean>();
  
  mobileOpen = false;
  user: User | null = null;
  currentRoute = '';
  private subs = new Subscription();
  private isBrowser: boolean;

  adminNavItems: NavItem[] = [
    { label: 'Tableau de bord', icon: 'bi bi-grid-1x2-fill', route: '/admin/dashboard' },
    { label: 'Utilisateurs', icon: 'bi bi-people-fill', route: '/admin/utilisateurs' },
    { label: 'Articles', icon: 'bi bi-file-text-fill', route: '/admin/articles' },
    { label: 'Clés API', icon: 'bi bi-key-fill', route: '/admin/api-keys' },
    { label: 'Statistiques', icon: 'bi bi-graph-up', route: '/admin/stats' },
    { label: 'Logs', icon: 'bi bi-journal-code', route: '/admin/logs' },
    { label: 'Paramètres', icon: 'bi bi-gear-fill', route: '/admin/parametres' },
  ];

  managerNavItems: NavItem[] = [
    { label: 'Tableau de bord', icon: 'bi bi-grid-1x2-fill', route: '/manager/dashboard' },
    { label: 'Mes sites', icon: 'bi bi-globe2', route: '/manager/sites' },
    { label: 'Rédaction IA', icon: 'bi bi-pencil-square', route: '/manager/redaction' },
    { label: 'Mes articles', icon: 'bi bi-file-text-fill', route: '/manager/articles' },
    { label: 'Configuration WP', icon: 'bi bi-wordpress', route: '/manager/wp-config' },
    
    // ============================================================
    // NOUVEAUX ONGLETS AJOUTÉS
    // ============================================================
    { label: 'Générer des médias', icon: 'bi bi-image-fill', route: '/manager/media/generate' },
    { label: 'Bibliothèque médias', icon: 'bi bi-collection-fill', route: '/manager/media/library' },
    { label: 'Réseaux sociaux', icon: 'bi bi-share-fill', route: '/manager/social-config' },
    
    { label: 'Paramètres', icon: 'bi bi-gear-fill', route: '/manager/parametres' },
  ];

  constructor(
    private authService: AuthService,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit(): void {
    if (this.isBrowser) {
      this.user = this.authService.getUser();
      this.currentRoute = this.router.url;

      this.subs.add(
        this.authService.currentUser$.subscribe(u => this.user = u)
      );
      this.subs.add(
        this.router.events
          .pipe(filter(e => e instanceof NavigationEnd))
          .subscribe((e: any) => this.currentRoute = e.urlAfterRedirects)
      );
    }
  }

  ngOnDestroy(): void {
    this.subs.unsubscribe();
  }

  get isAdmin(): boolean {
    return this.authService.isAdmin();
  }

  get navItems(): NavItem[] {
    return this.isAdmin ? this.adminNavItems : this.managerNavItems;
  }

  get userInitials(): string {
    if (!this.user) return '?';
    const first = this.user.firstname?.[0] ?? '';
    const last = this.user.name?.[0] ?? '';
    return (first + last).toUpperCase() || this.user.email[0].toUpperCase();
  }

  get userFullName(): string {
    if (!this.user) return '';
    return `${this.user.firstname ?? ''} ${this.user.name ?? ''}`.trim();
  }

  get roleLabel(): string {
    return this.user?.role === 'admin' ? 'Administrateur' : 'Manager';
  }

  isActive(route: string): boolean {
    return this.currentRoute.startsWith(route);
  }

  toggleCollapse(): void {
    if (!this.isBrowser) return;
    
    if (window.innerWidth < 768) {
      this.mobileOpen = !this.mobileOpen;
    } else {
      this.collapsed = !this.collapsed;
      this.collapsedChange.emit(this.collapsed);
    }
  }

  closeMobile(): void {
    if (this.isBrowser && window.innerWidth < 768) {
      this.mobileOpen = false;
    }
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/connexion']);
  }
}