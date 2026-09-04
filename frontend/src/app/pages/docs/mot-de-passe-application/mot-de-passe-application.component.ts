import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID, AfterViewInit } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-mot-de-passe-application',
  standalone: true,
  imports: [CommonModule, RouterModule, SidebarComponent],
  templateUrl: './mot-de-passe-application.component.html',
  styleUrls: ['./mot-de-passe-application.component.css']
})
export class MotDePasseApplicationComponent implements OnInit, AfterViewInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;

  private isBrowser: boolean;
  private observer: IntersectionObserver | null = null;

  constructor(
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit(): void {
    if (this.isBrowser) {
      window.scrollTo(0, 0);
    }
  }

  ngAfterViewInit(): void {
    if (this.isBrowser) {
      this.setupScrollAnimations();
    }
  }

  ngOnDestroy(): void {
    if (this.observer) {
      this.observer.disconnect();
    }
  }

  private setupScrollAnimations(): void {
    const elements = document.querySelectorAll('.animate-on-scroll');
    
    // Vérifier les éléments déjà visibles
    elements.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight - 100) {
        el.classList.add('visible');
      }
    });

    // Créer l'observateur pour les nouveaux éléments
    this.observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          this.observer?.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    elements.forEach(el => this.observer?.observe(el));
  }

  goBack(): void {
    this.router.navigate(['/manager/wp-config']);
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }
}