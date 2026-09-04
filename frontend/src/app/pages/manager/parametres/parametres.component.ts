// src/app/pages/manager/parametres/parametres.component.ts
import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { AuthService, User } from '../../../core/services/auth.service';
import { Subscription } from 'rxjs';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

@Component({
  selector: 'app-parametres',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, SidebarComponent],
  templateUrl: './parametres.component.html',
  styleUrls: ['./parametres.component.css']
})
export class ManagerParametresComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;

  // Formulaire profil
  profileForm: FormGroup;
  isSubmittingProfile = false;

  // Formulaire mot de passe
  passwordForm: FormGroup;
  isSubmittingPassword = false;
  showCurrentPassword = false;
  showNewPassword = false;
  showConfirmPassword = false;

  private subscriptions = new Subscription();
  private isBrowser: boolean;

  constructor(
    private authService: AuthService,
    private fb: FormBuilder,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    
    this.profileForm = this.fb.group({
      firstname: ['', [Validators.maxLength(50), Validators.pattern(/^[a-zA-ZÀ-ÿ\s]*$/)]],
      name: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(50), Validators.pattern(/^[a-zA-ZÀ-ÿ\s]+$/)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(100)]]
    });

    this.passwordForm = this.fb.group({
      current_password: ['', [Validators.required]],
      new_password: ['', [Validators.required, this.passwordValidator]],
      new_password_confirmation: ['', [Validators.required]]
    }, { validators: this.passwordMatchValidator });
  }

  // Validateur personnalisé pour le mot de passe
  passwordValidator(control: AbstractControl): ValidationErrors | null {
    const password = control.value;
    if (!password) return null;
    
    const errors: ValidationErrors = {};
    
    if (password.length < 10) errors['minlength'] = true;
    if (!/[A-Z]/.test(password)) errors['noUpperCase'] = true;
    if (!/[a-z]/.test(password)) errors['noLowerCase'] = true;
    if (!/\d/.test(password)) errors['noDigit'] = true;
    if (!/[@$!%*?&]/.test(password)) errors['noSpecialChar'] = true;
    
    return Object.keys(errors).length ? errors : null;
  }

  // Validation de confirmation du mot de passe
  passwordMatchValidator(group: FormGroup): ValidationErrors | null {
    const password = group.get('new_password')?.value;
    const confirm = group.get('new_password_confirmation')?.value;
    return password === confirm ? null : { mismatch: true };
  }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    // CORRECTION : Toujours charger les données, même si currentUser est null
    if (this.isBrowser) {
      this.loadUserData();
    }
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  // Méthode pour scroller en haut
  private scrollToTop(): void {
    const dashboardContent = document.querySelector('.dashboard-content');
    if (dashboardContent) {
      dashboardContent.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }
  }

  loadUserData(): void {
    this.isLoading = true;
    this.errorMessage = null;
    
    this.subscriptions.add(
      // CORRECTION : Utiliser getMe() au lieu de getCurrentUser()
      this.authService.getMe().subscribe({
        next: (user) => {
          this.currentUser = user;
          if (user) {
            this.profileForm.patchValue({
              firstname: user.firstname || '',
              name: user.name,
              email: user.email
            });
          }
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Erreur chargement profil:', error);
          this.errorMessage = error.message || 'Impossible de charger vos informations';
          this.isLoading = false;
          this.scrollToTop();
        }
      })
    );
  }

  // Soumission formulaire profil
  onSubmitProfile(): void {
    if (this.profileForm.invalid) {
      Object.keys(this.profileForm.controls).forEach(key => {
        this.profileForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSubmittingProfile = true;
    this.errorMessage = null;
    this.successMessage = null;

    const data: { name?: string; firstname?: string; email?: string } = {};
    
    if (this.profileForm.get('name')?.dirty) {
      data.name = this.profileForm.value.name.trim();
    }
    if (this.profileForm.get('firstname')?.dirty) {
      data.firstname = this.profileForm.value.firstname?.trim() || undefined;
    }
    if (this.profileForm.get('email')?.dirty) {
      data.email = this.profileForm.value.email.trim().toLowerCase();
    }

    if (Object.keys(data).length === 0) {
      this.successMessage = 'Aucune modification détectée';
      setTimeout(() => this.successMessage = null, 3000);
      this.isSubmittingProfile = false;
      this.scrollToTop();
      return;
    }

    this.subscriptions.add(
      this.authService.updateProfile(data).subscribe({
        next: (response: any) => {
          // CORRECTION : Gérer la réponse correctement
          if (response.user) {
            this.currentUser = response.user;
          } else if (response) {
            this.currentUser = response;
          }
          
          // Mettre à jour le formulaire
          if (this.currentUser) {
            this.profileForm.reset({
              firstname: this.currentUser.firstname || '',
              name: this.currentUser.name,
              email: this.currentUser.email
            });
          }
          
          this.successMessage = response.message || 'Profil mis à jour avec succès';
          setTimeout(() => this.successMessage = null, 3000);
          this.isSubmittingProfile = false;
          this.scrollToTop();
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors de la mise à jour du profil';
          if (error.details?.email) {
            this.errorMessage = error.details.email[0];
          }
          setTimeout(() => this.errorMessage = null, 3000);
          this.isSubmittingProfile = false;
          this.scrollToTop();
        }
      })
    );
  }

  // Soumission formulaire mot de passe
  onSubmitPassword(): void {
    if (this.passwordForm.invalid) {
      Object.keys(this.passwordForm.controls).forEach(key => {
        this.passwordForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isSubmittingPassword = true;
    this.errorMessage = null;
    this.successMessage = null;

    this.subscriptions.add(
      this.authService.changePassword({
        current_password: this.passwordForm.value.current_password,
        new_password: this.passwordForm.value.new_password,
        new_password_confirmation: this.passwordForm.value.new_password_confirmation
      }).subscribe({
        next: (response) => {
          this.successMessage = response.message || 'Mot de passe modifié avec succès';
          this.passwordForm.reset();
          setTimeout(() => this.successMessage = null, 3000);
          this.isSubmittingPassword = false;
          this.scrollToTop();
        },
        error: (error) => {
          this.errorMessage = error.message || 'Erreur lors du changement de mot de passe';
          setTimeout(() => this.errorMessage = null, 3000);
          this.isSubmittingPassword = false;
          this.scrollToTop();
        }
      })
    );
  }

  // Toggle password visibility
  toggleCurrentPassword(): void {
    this.showCurrentPassword = !this.showCurrentPassword;
  }

  toggleNewPassword(): void {
    this.showNewPassword = !this.showNewPassword;
  }

  toggleConfirmPassword(): void {
    this.showConfirmPassword = !this.showConfirmPassword;
  }

  get greeting(): string {
    if (!this.isBrowser) return 'Bonjour';
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  // CORRECTION : Vérification explicite que currentUser n'est pas null
  get firstName(): string {
    if (this.currentUser) {
      return this.currentUser.firstname ?? this.currentUser.name ?? 'Manager';
    }
    return 'Manager';
  }

  toggleSidebar(): void {
    if (!this.isBrowser) return;
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }

  refreshData(): void {
    this.loadUserData();
  }

  get pf() {
    return this.profileForm.controls;
  }

  get pwf() {
    return this.passwordForm.controls;
  }
}