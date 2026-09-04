import { Component, OnInit, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-accueil',
  imports: [CommonModule],
  templateUrl: './accueil.component.html',
  styleUrls: ['./accueil.component.css']
})
export class AccueilComponent implements OnInit, AfterViewInit {
  mobileMenuOpen = false;

  steps = [
    {
      number: '01',
      icon: 'bi bi-globe',
      title: 'Analyse du site & thèmes',
      description: 'L\'IA scrape votre site, détecte votre secteur, votre ton éditorial et votre public cible. Elle propose ensuite 5 thèmes d\'articles pertinents, adaptés à votre audience.',
      badge: 'Automatique'
    },
    {
      number: '02',
      icon: 'bi bi-file-text',
      title: 'Génération d\'article SEO',
      description: 'Choisissez un thème ou saisissez le vôtre. L\'IA rédige un article complet : titre H1, meta description, slug optimisé, structure H2/H3 et mots-clés intégrés.',
      badge: 'IA Générative'
    },
    {
      number: '03',
      icon: 'bi bi-wordpress',
      title: 'Publication WordPress',
      description: 'Relisez et modifiez si besoin, puis publiez immédiatement, programmez à une date précise, ou enregistrez en brouillon. Notre IA publie automatiquement à l\'heure choisie.',
      badge: 'Flexible'
    }
  ];

  ngOnInit(): void {}

  ngAfterViewInit(): void {
    // Petit délai pour s'assurer que le DOM est chargé
    setTimeout(() => {
      this.initScrollAnimations();
      this.initNavbarScroll();
    }, 100);
  }

  private initScrollAnimations(): void {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
          }
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -30px 0px' }
    );

    // Observer les éléments avec la classe .animate-on-scroll
    const elements = document.querySelectorAll('.animate-on-scroll');
    elements.forEach(el => observer.observe(el));
  }

  private initNavbarScroll(): void {
    window.addEventListener('scroll', () => {
      const navbar = document.querySelector('.navbar') as HTMLElement;
      if (navbar) {
        if (window.scrollY > 40) {
          navbar.classList.add('scrolled');
        } else {
          navbar.classList.remove('scrolled');
        }
      }
    });
  }

  scrollToSection(sectionId: string): void {
    const el = document.getElementById(sectionId);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      this.mobileMenuOpen = false;
    }
  }

  toggleMobileMenu(): void {
    this.mobileMenuOpen = !this.mobileMenuOpen;
  }
}