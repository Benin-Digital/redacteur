// src/app/pages//connexion.component.ts
import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-connexion',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './connexion.component.html',
  styleUrls: ['./connexion.component.css']
})
export class ConnexionComponent implements OnInit {
  loginForm: FormGroup;
  isLoading = false;
  errorMessage = '';
  showPassword = false;
  submitted = false;

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      remember: [false]
    });
  }

  ngOnInit(): void {
    if (this.authService.isAuthenticated()) {
      this.redirectByRole();
    }
  }

  get f() {
    return this.loginForm.controls;
  }

  togglePassword(): void {
    this.showPassword = !this.showPassword;
  }

  onSubmit(): void {
    this.submitted = true;
    this.errorMessage = '';

    if (this.loginForm.invalid) {
      Object.keys(this.loginForm.controls).forEach(key => {
        this.loginForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.isLoading = true;

    const email = this.f['email'].value;
    const password = this.f['password'].value;
    const remember = this.f['remember'].value;

    this.authService.login(email, password).subscribe({
      next: (response) => {
        this.isLoading = false;
        
        if (remember) {
          localStorage.setItem('remember_me', 'true');
        } else {
          localStorage.removeItem('remember_me');
        }
        
        // VÉRIFICATION 2FA - Redirection SANS userId dans l'URL
        if (response.requires_two_factor && response.user_id) {
          // Stocker uniquement l'userId dans localStorage
          this.authService.setTwoFactorData(response.user_id, response.expires_in || 600);
          
          // Rediriger vers la page de vérification (sans paramètres)
          this.router.navigate(['/verification']);
        } else {
          this.redirectByRole();
        }
      },
      error: (error) => {
        this.isLoading = false;
        
        if (error.message) {
          this.errorMessage = error.message;
        } else if (error.error?.message) {
          this.errorMessage = error.error.message;
        } else {
          this.errorMessage = 'Email ou mot de passe incorrect';
        }
        
        if (error.status === 403 && error.error?.account_disabled) {
          this.errorMessage = 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.';
        }
      }
    });
  }

  private redirectByRole(): void {
    const user = this.authService.getUser();
    
    if (user?.role === 'admin') {
      this.router.navigate(['/admin/dashboard']);
    } else if (user?.role === 'manager') {
      this.router.navigate(['/manager/dashboard']);
    } else {
      this.router.navigate(['/']);
    }
  }
}