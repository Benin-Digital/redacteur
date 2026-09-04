import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { finalize } from 'rxjs/operators';

@Component({
  selector: 'app-verification',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './verification.component.html',
  styleUrls: ['./verification.component.css']
})
export class VerificationComponent implements OnInit, OnDestroy {
  verificationForm!: FormGroup;
  submitted = false;
  isLoading = false;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  
  // Timer pour le code (10 minutes)
  timerSeconds = 600;
  timerInterval: any;
  isExpired = false;
  
  // Données de l'utilisateur
  userId!: number;
  email: string = '';

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.initForm();
    this.getStoredData();
    this.startTimer();
  }

  ngOnDestroy(): void {
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
    }
  }

  private initForm(): void {
    this.verificationForm = this.fb.group({
      code: ['', [
        Validators.required,
        Validators.minLength(6),
        Validators.maxLength(6),
        Validators.pattern(/^[0-9]{6}$/)
      ]]
    });
  }

  /**
   * Récupérer l'userId depuis localStorage UNIQUEMENT
   * L'email n'est pas nécessaire, on l'affiche seulement si présent
   */
  private getStoredData(): void {
    const storedUserId = this.authService.getTwoFactorUserId();
    if (storedUserId) {
      this.userId = storedUserId;
    }
    
    // Optionnel : récupérer l'email pour l'affichage
    const storedEmail = this.authService.getTwoFactorEmail();
    if (storedEmail) {
      this.email = storedEmail;
    }
    
    const storedExpires = this.authService.getTwoFactorExpiresIn();
    if (storedExpires) {
      this.timerSeconds = storedExpires;
    }
    
    if (!this.userId) {
      this.router.navigate(['/connexion']);
    }
  }

  private startTimer(): void {
    this.isExpired = false;
    
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
    }
    
    this.timerInterval = setInterval(() => {
      this.timerSeconds--;
      if (this.timerSeconds <= 0) {
        this.timerSeconds = 0;
        this.isExpired = true;
        clearInterval(this.timerInterval);
      }
    }, 1000);
  }

  get f() {
    return this.verificationForm.controls;
  }

  onCodeInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    input.value = input.value.replace(/\D/g, '');
    if (input.value.length > 6) {
      input.value = input.value.slice(0, 6);
    }
  }

  onSubmit(): void {
    this.submitted = true;
    this.clearMessages();

    if (this.verificationForm.invalid) {
      return;
    }

    if (this.isExpired) {
      this.errorMessage = 'Le code a expiré. Veuillez demander un nouveau code.';
      return;
    }

    this.isLoading = true;
    const code = this.verificationForm.value.code;

    this.authService.verifyTwoFactor({
      user_id: this.userId,
      code: code
    }).pipe(
      finalize(() => {
        this.isLoading = false;
      })
    ).subscribe({
      next: (response) => {
        if (response.access_token && response.user) {
          // Nettoyer les données 2FA
          this.authService.clearTwoFactorData();
          
          this.successMessage = 'Vérification réussie ! Redirection en cours...';
          setTimeout(() => {
            if (response.user?.role === 'admin') {
              this.router.navigate(['/admin/dashboard']);
            } else {
              this.router.navigate(['/']);
            }
          }, 1500);
        } else {
          this.errorMessage = 'Erreur lors de la vérification. Veuillez réessayer.';
        }
      },
      error: (error) => {
        this.errorMessage = error.message || 'Code invalide. Veuillez réessayer.';
        this.verificationForm.patchValue({ code: '' });
        this.verificationForm.markAsPristine();
      }
    });
  }

  resendCode(): void {
    this.clearMessages();
    
    if (!this.userId) {
      this.errorMessage = 'Impossible de renvoyer le code. Utilisateur non identifié.';
      return;
    }

    this.isLoading = true;

    this.authService.resendVerificationCode(this.userId).pipe(
      finalize(() => {
        this.isLoading = false;
      })
    ).subscribe({
      next: (response) => {
        this.successMessage = 'Un nouveau code vous a été envoyé par email.';
        if (response.expires_in) {
          this.timerSeconds = response.expires_in;
        }
        this.startTimer();
        this.verificationForm.patchValue({ code: '' });
        this.verificationForm.markAsPristine();
        this.submitted = false;
      },
      error: (error) => {
        this.errorMessage = error.message || 'Impossible de renvoyer le code. Veuillez réessayer.';
      }
    });
  }

  goToLogin(): void {
    // Nettoyer toutes les données 2FA
    this.authService.clearTwoFactorData();
    this.router.navigate(['/connexion']);
  }

  clearMessages(): void {
    this.errorMessage = null;
    this.successMessage = null;
  }

  getTimeFormatted(): string {
    const minutes = Math.floor(this.timerSeconds / 60);
    const seconds = this.timerSeconds % 60;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  }
}