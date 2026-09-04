import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, AbstractControl, ReactiveFormsModule } from '@angular/forms';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-nouveau-mot-de-passe',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './nouveau-mot-de-passe.component.html',
  styleUrls: ['./nouveau-mot-de-passe.component.css']
})
export class NouveauMotDePasseComponent implements OnInit {
  resetForm: FormGroup;
  isLoading = false;
  errorMessage = '';
  successMessage = '';
  token: string = '';
  email: string = '';
  showPassword = false;
  showConfirmPassword = false;

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {
    this.resetForm = this.fb.group({
      password: ['', [
        Validators.required,
        Validators.minLength(10),
        Validators.pattern(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/)
      ]],
      password_confirmation: ['', Validators.required]
    }, { validators: this.passwordMatchValidator });
  }

  ngOnInit(): void {
    this.route.queryParams.subscribe(params => {
      this.token = params['token'] || '';
      this.email = params['email'] || '';

      if (!this.token || !this.email) {
        this.errorMessage = 'Lien de réinitialisation invalide. Veuillez refaire une demande.';
      }
    });
  }

  get f() {
    return this.resetForm.controls;
  }

  togglePassword(): void {
    this.showPassword = !this.showPassword;
  }

  toggleConfirmPassword(): void {
    this.showConfirmPassword = !this.showConfirmPassword;
  }

  passwordMatchValidator(group: FormGroup): { [key: string]: boolean } | null {
    const password = group.get('password')?.value;
    const confirm = group.get('password_confirmation')?.value;
    return password === confirm ? null : { mismatch: true };
  }

  onSubmit(): void {
    if (this.resetForm.invalid) {
      Object.keys(this.resetForm.controls).forEach(key => {
        this.resetForm.get(key)?.markAsTouched();
      });
      return;
    }

    if (!this.token || !this.email) {
      this.errorMessage = 'Lien de réinitialisation invalide';
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';
    this.successMessage = '';

    this.authService.resetPassword({
      token: this.token,
      email: this.email,
      password: this.f['password'].value,
      password_confirmation: this.f['password_confirmation'].value
    }).subscribe({
      next: (response) => {
        this.isLoading = false;
        this.successMessage = response.message || 'Mot de passe mis à jour avec succès. Redirection...';
        setTimeout(() => {
          this.router.navigate(['/connexion']);
        }, 3000);
      },
      error: (error) => {
        this.isLoading = false;

        if (error.error?.message) {
          this.errorMessage = error.error.message;
        } else if (error.error?.errors?.password?.[0]) {
          this.errorMessage = error.error.errors.password[0];
        } else if (error.message) {
          this.errorMessage = error.message;
        } else {
          this.errorMessage = 'Une erreur est survenue. Veuillez réessayer.';
        }
      }
    });
  }

  // Vérifications pour l'affichage des critères du mot de passe
  hasUpperCase(): boolean {
    const password = this.f['password']?.value || '';
    return /[A-Z]/.test(password);
  }

  hasLowerCase(): boolean {
    const password = this.f['password']?.value || '';
    return /[a-z]/.test(password);
  }

  hasDigit(): boolean {
    const password = this.f['password']?.value || '';
    return /\d/.test(password);
  }

  hasSpecialChar(): boolean {
    const password = this.f['password']?.value || '';
    return /[@$!%*?&]/.test(password);
  }

  hasMinLength(): boolean {
    const password = this.f['password']?.value || '';
    return password.length >= 10;
  }
}