// frontend/src/app/pages/manager/social-config/tiktok-callback/tiktok-callback.component.ts

import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

@Component({
  selector: 'app-tiktok-callback',
  standalone: true,
  imports: [CommonModule, RouterModule],
  template: `
    <div class="callback-container">
      <div class="callback-card">
        @if (isLoading) {
          <div class="loading-state">
            <div class="spinner"></div>
            <p class="loading-text">Connexion à TikTok en cours...</p>
            <p class="loading-subtext">Veuillez patienter, nous traitons votre demande.</p>
          </div>
        } @else if (isSuccess) {
          <div class="success-state">
            <div class="icon-circle success">
              <i class="bi bi-check-lg"></i>
            </div>
            <h2 class="title">Connexion TikTok réussie !</h2>
            <p class="message">Votre compte TikTok a été connecté avec succès.</p>
            @if (accountInfo) {
              <div class="account-info">
                @if (accountInfo.profile_picture) {
                  <div class="avatar">
                    <img [src]="accountInfo.profile_picture" [alt]="accountInfo.account_name">
                  </div>
                }
                <div class="account-details">
                  <span class="account-name">{{ accountInfo.account_name || 'Compte TikTok' }}</span>
                  <span class="account-username">&#64;{{ accountInfo.account_username || 'username' }}</span>
                </div>
              </div>
            }
            <div class="actions">
              <button class="btn-primary" (click)="goToSocialConfig()">
                <i class="bi bi-arrow-left"></i> Retour à la configuration
              </button>
              <button class="btn-secondary" (click)="goToLibrary()">
                <i class="bi bi-collection"></i> Voir mes médias
              </button>
            </div>
          </div>
        } @else {
          <div class="error-state">
            <div class="icon-circle error">
              <i class="bi bi-x-lg"></i>
            </div>
            <h2 class="title">❌ Erreur de connexion</h2>
            <p class="message">{{ errorMessage || 'Une erreur est survenue lors de la connexion à TikTok.' }}</p>
            @if (errorDetails) {
              <div class="error-details">
                <code>{{ errorDetails }}</code>
              </div>
            }
            <div class="actions">
              <button class="btn-primary" (click)="goToSocialConfig()">
                <i class="bi bi-arrow-left"></i> Retour à la configuration
              </button>
              <button class="btn-secondary" (click)="retryConnect()">
                <i class="bi bi-arrow-repeat"></i> Réessayer
              </button>
            </div>
          </div>
        }
      </div>
    </div>
  `,
  styles: [`
    .callback-container {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      padding: 1.5rem;
      margin: 0;
    }

    .callback-card {
      background: #ffffff;
      border-radius: 24px;
      padding: 3rem 2.5rem;
      max-width: 520px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
      text-align: center;
      animation: fadeInUp 0.5s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Loading */
    .loading-state {
      padding: 1rem 0;
    }

    .spinner {
      width: 56px;
      height: 56px;
      border: 4px solid #e5e7eb;
      border-top-color: #28b188;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 1.5rem;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .loading-text {
      font-size: 1.1rem;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 0.5rem;
    }

    .loading-subtext {
      font-size: 0.9rem;
      color: #6b7280;
    }

    /* Success & Error */
    .icon-circle {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2rem;
    }

    .icon-circle.success {
      background: #d1fae5;
      color: #065f46;
    }

    .icon-circle.error {
      background: #fecaca;
      color: #991b1b;
    }

    .title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 0.75rem;
    }

    .message {
      font-size: 1rem;
      color: #4b5563;
      margin-bottom: 1.5rem;
      line-height: 1.6;
    }

    /* Account Info */
    .account-info {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      background: #f9fafb;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      text-align: left;
    }

    .avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      overflow: hidden;
      flex-shrink: 0;
      background: #e5e7eb;
    }

    .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .account-details {
      display: flex;
      flex-direction: column;
    }

    .account-name {
      font-weight: 600;
      color: #1f2937;
    }

    .account-username {
      font-size: 0.85rem;
      color: #6b7280;
    }

    /* Error Details */
    .error-details {
      background: #f3f4f6;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      text-align: left;
      overflow: auto;
    }

    .error-details code {
      font-size: 0.8rem;
      color: #6b7280;
      word-break: break-all;
      white-space: pre-wrap;
    }

    /* Actions */
    .actions {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      margin-top: 0.5rem;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: #28b188;
      color: #ffffff;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.2s ease;
      width: 100%;
    }

    .btn-primary:hover {
      background: #1e8a6a;
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(40, 177, 136, 0.3);
    }

    .btn-secondary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.2s ease;
      width: 100%;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
      transform: translateY(-2px);
    }

    /* Responsive */
    @media (max-width: 480px) {
      .callback-card {
        padding: 2rem 1.5rem;
      }

      .title {
        font-size: 1.25rem;
      }
    }
  `]
})
export class TiktokCallbackComponent implements OnInit {
  isLoading = true;
  isSuccess = false;
  errorMessage: string | null = null;
  errorDetails: string | null = null;
  accountInfo: any = null;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient
  ) {}

  ngOnInit(): void {
    // Récupérer les paramètres de l'URL
    this.route.queryParams.subscribe(params => {
      const success = params['success'];
      const error = params['error'];
      const errorDescription = params['error_description'];

      if (success === 'connected') {
        this.isSuccess = true;
        this.isLoading = false;
        this.fetchAccountInfo();
      } else if (error) {
        this.isSuccess = false;
        this.isLoading = false;
        this.errorMessage = errorDescription || error;
        this.errorDetails = error;
      } else {
        // Pas de paramètres, rediriger vers la configuration
        this.router.navigate(['/manager/social-config']);
      }
    });
  }

  private fetchAccountInfo(): void {
    const url = `${environment.apiUrl}/social/tiktok/status`;
    this.http.get<any>(url).subscribe({
      next: (response) => {
        if (response.is_connected) {
          this.accountInfo = {
            account_name: response.account_name,
            account_username: response.account_username,
            profile_picture: response.profile_picture,
            expires_at: response.expires_at
          };
        }
        this.isLoading = false;
      },
      error: () => {
        this.isLoading = false;
      }
    });
  }

  goToSocialConfig(): void {
    this.router.navigate(['/manager/social-config']);
  }

  goToLibrary(): void {
    this.router.navigate(['/manager/media/library']);
  }

  retryConnect(): void {
    this.router.navigate(['/manager/social-config']);
    // La connexion sera gérée par le composant social-config
  }
}