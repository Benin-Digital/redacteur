import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AuthService, User } from '../../../core/services/auth.service';
import { AdminService, Manager, CreateManagerPayload } from '../../../core/services/admin.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-utilisateurs',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, SidebarComponent],
  templateUrl: './utilisateurs.component.html',
  styleUrls: ['./utilisateurs.component.css']
})
export class AdminUtilisateursComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;

  // Données
  managers: Manager[] = [];
  currentPage = 1;
  lastPage = 1;
  totalItems = 0;

  // Modal
  showModal = false;
  modalTitle = '';
  isEditing = false;
  selectedManager: Manager | null = null;

  // Formulaire
  managerForm: FormGroup;
  isSubmitting = false;

  // Confirmation de suppression
  showDeleteConfirm = false;
  managerToDelete: Manager | null = null;

  // Confirmation de changement de statut
  showToggleConfirm = false;
  managerToToggle: Manager | null = null;

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  constructor(
    private authService: AuthService,
    private adminService: AdminService,
    private fb: FormBuilder,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    this.managerForm = this.createForm();
  }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    if (this.isBrowser) {
      this.loadManagers();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  private createForm(): FormGroup {
    return this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(50), Validators.pattern(/^[a-zA-Z\s]+$/)]],
      firstname: ['', [Validators.maxLength(50), Validators.pattern(/^[a-zA-Z\s]*$/)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(100)]]
    });
  }

  loadManagers(page: number = 1): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.adminService.listManagers(page).subscribe({
        next: (response) => {
          this.managers = response.data;
          this.currentPage = response.current_page;
          this.lastPage = response.last_page;
          this.totalItems = response.total;
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement managers:', error);
          this.errorMessage = error.message || 'Impossible de charger la liste des managers';
          this.isLoading = false;
        }
      })
    );
  }

  // Pagination
  goToPage(page: number): void {
    if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
      this.loadManagers(page);
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

  // Modal - Ajout
  openAddModal(): void {
    this.modalTitle = 'Ajouter un manager';
    this.isEditing = false;
    this.selectedManager = null;
    this.managerForm.reset({
      name: '',
      firstname: '',
      email: ''
    });
    this.errorMessage = null;
    this.successMessage = null;
    this.showModal = true;
  }

  // Modal - Modification
  openEditModal(manager: Manager): void {
    this.modalTitle = 'Modifier le manager';
    this.isEditing = true;
    this.selectedManager = manager;
    this.managerForm.patchValue({
      name: manager.name,
      firstname: manager.firstname || '',
      email: manager.email
    });
    this.errorMessage = null;
    this.successMessage = null;
    this.showModal = true;
  }

  closeModal(): void {
    this.showModal = false;
    this.selectedManager = null;
    this.managerForm.reset();
    this.errorMessage = null;
  }

  // Soumission du formulaire
  onSubmit(): void {
    if (this.managerForm.invalid) {
      Object.keys(this.managerForm.controls).forEach(key => {
        this.managerForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSubmitting = true;
    this.errorMessage = null;
    this.successMessage = null;

    if (this.isEditing && this.selectedManager) {
      this.updateManager();
    } else {
      this.createManager();
    }
  }

  private createManager(): void {
    const payload: CreateManagerPayload = {
      name: this.managerForm.value.name.trim(),
      firstname: this.managerForm.value.firstname?.trim() || undefined,
      email: this.managerForm.value.email.trim().toLowerCase()
    };

    this.subscriptions.add(
      this.adminService.createManager(payload).subscribe({
        next: (response) => {
          this.successMessage = response.message;
          setTimeout(() => {
            this.closeModal();
            this.loadManagers(this.currentPage);
          }, 1500);
          this.isSubmitting = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la création';
          if (error.details?.email) {
            this.errorMessage = error.details.email[0];
          }
          this.isSubmitting = false;
        }
      })
    );
  }

  private updateManager(): void {
    if (!this.selectedManager) return;

    const payload: Partial<CreateManagerPayload> = {};
    if (this.managerForm.get('name')?.dirty) {
      payload.name = this.managerForm.value.name.trim();
    }
    if (this.managerForm.get('firstname')?.dirty) {
      payload.firstname = this.managerForm.value.firstname?.trim() || undefined;
    }
    if (this.managerForm.get('email')?.dirty) {
      payload.email = this.managerForm.value.email.trim().toLowerCase();
    }

    if (Object.keys(payload).length === 0) {
      this.closeModal();
      return;
    }

    this.subscriptions.add(
      this.adminService.updateManager(this.selectedManager.id, payload).subscribe({
        next: (response) => {
          this.successMessage = response.message;
          setTimeout(() => {
            this.closeModal();
            this.loadManagers(this.currentPage);
          }, 1500);
          this.isSubmitting = false;
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la modification';
          if (error.details?.email) {
            this.errorMessage = error.details.email[0];
          }
          this.isSubmitting = false;
        }
      })
    );
  }

  // Confirmation suppression
  confirmDelete(manager: Manager): void {
    this.managerToDelete = manager;
    this.showDeleteConfirm = true;
  }

  cancelDelete(): void {
    this.showDeleteConfirm = false;
    this.managerToDelete = null;
  }

  deleteManager(): void {
    if (!this.managerToDelete) return;

    this.isSubmitting = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.adminService.deleteManager(this.managerToDelete.id).subscribe({
        next: () => {
          this.showDeleteConfirm = false;
          this.managerToDelete = null;
          this.loadManagers(this.currentPage);
          this.isSubmitting = false;
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

  // Confirmation changement de statut
  confirmToggleStatus(manager: Manager): void {
    if (manager.id === this.currentUser?.id) {
      this.errorMessage = 'Vous ne pouvez pas modifier votre propre statut';
      setTimeout(() => {
        this.errorMessage = null;
      }, 3000);
      return;
    }

    this.managerToToggle = manager;
    this.showToggleConfirm = true;
  }

  cancelToggleStatus(): void {
    this.showToggleConfirm = false;
    this.managerToToggle = null;
  }

  executeToggleStatus(): void {
    if (!this.managerToToggle) return;

    this.isSubmitting = true;
    this.errorMessage = null;

    this.subscriptions.add(
      this.adminService.toggleManagerStatus(this.managerToToggle.id).subscribe({
        next: (response) => {
          // Mettre à jour le manager dans la liste
          const index = this.managers.findIndex(m => m.id === this.managerToToggle?.id);
          if (index !== -1 && this.managerToToggle) {
            this.managerToToggle.is_active = response.is_active;
            this.managers[index] = { ...this.managerToToggle };
          }
          
          this.successMessage = response.message;
          this.showToggleConfirm = false;
          this.managerToToggle = null;
          this.isSubmitting = false;
          
          setTimeout(() => {
            this.successMessage = null;
          }, 3000);
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors du changement de statut';
          this.isSubmitting = false;
          setTimeout(() => {
            this.errorMessage = null;
          }, 3000);
        }
      })
    );
  }

  // Helpers
  getInitials(name: string, firstname?: string): string {
    if (firstname && firstname.length > 0) {
      return (firstname[0] + (name[0] || '')).toUpperCase();
    }
    return (name[0] || '?').toUpperCase();
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

  refreshData(): void {
    this.loadManagers(this.currentPage);
  }
}