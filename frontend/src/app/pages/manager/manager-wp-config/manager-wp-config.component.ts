import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AuthService, User } from '../../../core/services/auth.service';
import { ManagerService, Site, WordPressConfig, WordPressConfigPayload } from '../../../core/services/manager.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-manager-wp-config',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, SidebarComponent],
  templateUrl: './manager-wp-config.component.html',
  styleUrls: ['./manager-wp-config.component.css']
})
export class ManagerWpConfigComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  isTesting = false;

  // Données
  sites: Site[] = [];
  selectedSite: Site | null = null;
  currentConfig: WordPressConfig | null = null;

  // Formulaire
  configForm: FormGroup;
  isSubmitting = false;
  showPassword = false;

  // Modal
  showModal = false;

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  constructor(
    private authService: AuthService,
    private managerService: ManagerService,
    private fb: FormBuilder,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    this.configForm = this.createForm();
  }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    if (this.isBrowser) {
      this.loadSites();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private createForm(): FormGroup {
    return this.fb.group({
      wp_url: ['', [Validators.required, Validators.pattern('https?://.+')]],
      wp_username: ['', [Validators.required, Validators.minLength(2)]],
      wp_app_password: ['', [Validators.required, Validators.minLength(10)]],
      test_connection: [true]
    });
  }

  loadSites(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.managerService.listSites(1).subscribe({
        next: (response) => {
          this.sites = response.data;
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement sites:', error);
          this.errorMessage = error.message || 'Impossible de charger la liste des sites';
          this.isLoading = false;
        }
      })
    );
  }

  selectSite(site: Site): void {
    this.selectedSite = site;
    this.errorMessage = null;
    this.successMessage = null;
    this.currentConfig = null;

    // Charger la configuration existante
    this.subscriptions.add(
      this.managerService.getSiteWpConfig(site.id).subscribe({
        next: (config) => {
          this.currentConfig = config;
          this.configForm.patchValue({
            wp_url: config.wp_url,
            wp_username: config.wp_username,
            wp_app_password: '',
            test_connection: false
          });
        },
        error: () => {
          // Pas de configuration existante, formulaire vide
          this.currentConfig = null;
          this.configForm.reset({
            wp_url: '',
            wp_username: '',
            wp_app_password: '',
            test_connection: true
          });
        }
      })
    );
  }

  openModal(): void {
    this.showModal = true;
  }

  closeModal(): void {
    this.showModal = false;
    this.errorMessage = null;
  }

  onSubmit(): void {
    if (!this.selectedSite || this.configForm.invalid) {
      Object.keys(this.configForm.controls).forEach(key => {
        this.configForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSubmitting = true;
    this.errorMessage = null;
    this.successMessage = null;

    const payload: WordPressConfigPayload = {
      wp_url: this.configForm.value.wp_url,
      wp_username: this.configForm.value.wp_username,
      wp_app_password: this.configForm.value.wp_app_password,
      test_connection: this.configForm.value.test_connection
    };

    this.subscriptions.add(
      this.managerService.saveSiteWpConfig(this.selectedSite.id, payload).subscribe({
        next: (response) => {
          this.successMessage = response.message;
          if (response.connection_test) {
            this.successMessage += ` - Test: ${response.connection_test.message}`;
          }
          this.closeModal();
          // Recharger la configuration
          this.selectSite(this.selectedSite!);
          setTimeout(() => this.successMessage = null, 4000);
          this.isSubmitting = false;
        },
        error: (error) => {
          this.errorMessage = error.message;
          this.isSubmitting = false;
          setTimeout(() => this.errorMessage = null, 4000);
        }
      })
    );
  }

  testConnection(): void {
    if (!this.selectedSite) return;

    this.isTesting = true;
    this.errorMessage = null;
    this.successMessage = null;

    this.subscriptions.add(
      this.managerService.testWpConnection(this.selectedSite.id).subscribe({
        next: (result) => {
          if (result.success) {
            this.successMessage = `Test réussi : ${result.message}`;
            if (result.user) {
              this.successMessage += ` (Utilisateur: ${result.user})`;
            }
            // IMPORTANT: Recharger la configuration pour mettre à jour last_test_status
            this.refreshCurrentConfig();
          } else {
            this.errorMessage = `❌ Test échoué : ${result.message}`;
          }
          this.isTesting = false;
          setTimeout(() => {
            this.successMessage = null;
            this.errorMessage = null;
          }, 5000);
        },
        error: (error) => {
          this.errorMessage = `❌ Erreur lors du test : ${error.message}`;
          this.isTesting = false;
          setTimeout(() => this.errorMessage = null, 5000);
        }
      })
    );
  }

  refreshCurrentConfig(): void {
    if (!this.selectedSite) return;

    this.subscriptions.add(
      this.managerService.getSiteWpConfig(this.selectedSite.id).subscribe({
        next: (config) => {
          this.currentConfig = config;
        },
        error: () => {
          // Ignorer l'erreur
        }
      })
    );
  }

  deleteConfig(): void {
    if (!this.selectedSite) return;

    if (confirm(`Supprimer la configuration WordPress pour ${this.selectedSite.url} ?`)) {
      this.isSubmitting = true;

      this.subscriptions.add(
        this.managerService.deleteSiteWpConfig(this.selectedSite.id).subscribe({
          next: () => {
            this.successMessage = 'Configuration supprimée avec succès';
            this.currentConfig = null;
            this.configForm.reset({
              wp_url: '',
              wp_username: '',
              wp_app_password: '',
              test_connection: true
            });
            setTimeout(() => this.successMessage = null, 3000);
            this.isSubmitting = false;
          },
          error: (error) => {
            this.errorMessage = error.message;
            this.isSubmitting = false;
            setTimeout(() => this.errorMessage = null, 3000);
          }
        })
      );
    }
  }

  refreshData(): void {
    this.loadSites();
    if (this.selectedSite) {
      this.selectSite(this.selectedSite);
    }
  }

  // CORRECTION ICI : Ajout de | null
  formatDate(dateStr?: string | null): string {
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
    return this.currentUser?.firstname ?? this.currentUser?.name ?? 'Manager';
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }

  get hasConfig(): boolean {
    return this.currentConfig !== null;
  }
}