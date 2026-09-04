// social-config.component.ts

import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService, User } from '../../../core/services/auth.service';
import { SidebarComponent } from '../../../shared/components/sidebar/sidebar/sidebar.component';

interface PlatformStatus {
  platform: string;
  label: string;
  icon: string;
  color: string;
  is_connected: boolean;
  account_name: string | null;
  account_username: string | null;
  profile_picture: string | null;
  expires_at: string | null;
  is_token_expired: boolean;
  needs_config: boolean;
  auth_url: string | null;
  state: string | null;
  is_connecting: boolean;
}

@Component({
  selector: 'app-social-config',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, SidebarComponent],
  templateUrl: './social-config.component.html',
  styleUrl: './social-config.component.css'
})
export class SocialConfigComponent implements OnInit {
  sidebarCollapsed = false;
  mobileSidebarOpen = false;
  currentUser: User | null = null;

  // États
  isLoading = false;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  isDisconnecting = false;

  // Import token
  showTokenModal = false;
  importTokenPlatform: 'instagram' | 'tiktok' = 'instagram';
  accessToken: string = '';
  userId: string = '';
  username: string = '';
  isImporting = false;

  // Plateformes
  platforms: PlatformStatus[] = [
    {
      platform: 'tiktok',
      label: 'TikTok',
      icon: 'bi-tiktok',
      color: '#000000',
      is_connected: false,
      account_name: null,
      account_username: null,
      profile_picture: null,
      expires_at: null,
      is_token_expired: false,
      needs_config: true,
      auth_url: null,
      state: null,
      is_connecting: false
    },
    {
      platform: 'instagram',
      label: 'Instagram',
      icon: 'bi-instagram',
      color: '#E4405F',
      is_connected: false,
      account_name: null,
      account_username: null,
      profile_picture: null,
      expires_at: null,
      is_token_expired: false,
      needs_config: true,
      auth_url: null,
      state: null,
      is_connecting: false
    }
  ];

  // Pour le modal de déconnexion
  showDisconnectModal = false;
  platformToDisconnect: string | null = null;

  // Timer pour les messages
  private messageTimeout: any = null;

  // Import TikTok credentials
  showTikTokModal = false;
  tiktokClientKey: string = '';
  tiktokClientSecret: string = '';
  tiktokRedirectUri: string = '';
  isImportingTikTok = false;

  constructor(
    private http: HttpClient,
    private authService: AuthService
  ) { }

  ngOnInit(): void {
    this.currentUser = this.authService.getUser();
    this.loadAllStatus();
  }

  // ============================================================
  // GESTION DES MESSAGES
  // ============================================================

  setErrorMessage(message: string, duration: number = 6000): void {
    // Nettoyer le timeout précédent
    if (this.messageTimeout) {
      clearTimeout(this.messageTimeout);
      this.messageTimeout = null;
    }

    // Effacer le message de succès
    this.successMessage = null;

    // Afficher l'erreur
    this.errorMessage = message;
    this.messageTimeout = setTimeout(() => {
      this.errorMessage = null;
      this.messageTimeout = null;
    }, duration);
  }

  setSuccessMessage(message: string, duration: number = 6000): void {
    // Nettoyer le timeout précédent
    if (this.messageTimeout) {
      clearTimeout(this.messageTimeout);
      this.messageTimeout = null;
    }

    // Effacer le message d'erreur
    this.errorMessage = null;

    // Afficher le succès
    this.successMessage = message;
    this.messageTimeout = setTimeout(() => {
      this.successMessage = null;
      this.messageTimeout = null;
    }, duration);
  }

  clearMessages(): void {
    if (this.messageTimeout) {
      clearTimeout(this.messageTimeout);
      this.messageTimeout = null;
    }
    this.errorMessage = null;
    this.successMessage = null;
  }

  // ============================================================
  // SCROLL EN HAUT DE PAGE
  // ============================================================

  scrollToTop(): void {
    setTimeout(() => {
      const content = document.querySelector('.dashboard-content');
      if (content) {
        content.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }, 200);
  }

  // ============================================================
  // CHARGEMENT DES STATUTS
  // ============================================================

  loadAllStatus(): void {
    this.isLoading = true;
    this.clearMessages();

    Promise.all([
      this.loadPlatformStatus('tiktok'),
      this.loadPlatformStatus('instagram')
    ]).finally(() => {
      this.isLoading = false;
    });
  }

  // ============================================================
  // IMPORT TIKTOK CREDENTIALS
  // ============================================================

  openImportTikTokModal(): void {
    this.tiktokClientKey = '';
    this.tiktokClientSecret = '';
    this.tiktokRedirectUri = `${environment.apiUrl}/social/tiktok/callback`;
    this.isImportingTikTok = false;
    this.clearMessages();
    this.showTikTokModal = true;
  }

  closeImportTikTokModal(): void {
    this.showTikTokModal = false;
    this.tiktokClientKey = '';
    this.tiktokClientSecret = '';
    this.tiktokRedirectUri = '';
    this.isImportingTikTok = false;
  }

  importTikTokCredentials(): void {
    if (!this.tiktokClientKey || this.tiktokClientKey.trim() === '') {
      this.setErrorMessage('Veuillez entrer le Client Key TikTok');
      return;
    }

    if (!this.tiktokClientSecret || this.tiktokClientSecret.trim() === '') {
      this.setErrorMessage('Veuillez entrer le Client Secret TikTok');
      return;
    }

    this.isImportingTikTok = true;
    this.clearMessages();

    const url = `${environment.apiUrl}/social/tiktok/import-credentials`;
    this.http.post(url, {
      client_key: this.tiktokClientKey.trim(),
      client_secret: this.tiktokClientSecret.trim(),
      redirect_uri: this.tiktokRedirectUri.trim()
    }).subscribe({
      next: (response: any) => {
        this.isImportingTikTok = false;
        this.closeImportTikTokModal();

        if (response.auth_url) {
          // Ouvrir la popup d'authentification TikTok
          const width = 600;
          const height = 700;
          const left = (window.innerWidth - width) / 2;
          const top = (window.innerHeight - height) / 2;

          const popup = window.open(
            response.auth_url,
            'connect_tiktok',
            `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,scrollbars=yes`
          );

          // Vérifier périodiquement si la connexion est établie
          const checkInterval = setInterval(() => {
            this.loadPlatformStatus('tiktok').then(() => {
              const platform = this.platforms.find(p => p.platform === 'tiktok');
              if (platform?.is_connected) {
                clearInterval(checkInterval);
                this.setSuccessMessage('Compte TikTok connecté avec succès !');
                this.scrollToTop();
                if (popup && !popup.closed) {
                  popup.close();
                }
              }
            });
          }, 3000);

          setTimeout(() => {
            clearInterval(checkInterval);
          }, 300000);
        } else {
          this.setSuccessMessage('Credentials TikTok importés avec succès !');
          this.scrollToTop();
          this.loadAllStatus();
        }
      },
      error: (error) => {
        this.isImportingTikTok = false;
        this.setErrorMessage(error.error?.message || 'Erreur lors de l\'import des credentials TikTok');
        this.scrollToTop();
      }
    });
  }

  private loadPlatformStatus(platform: string): Promise<void> {
    return new Promise((resolve) => {
      const url = `${environment.apiUrl}/social/${platform}/status`;
      this.http.get<any>(url).subscribe({
        next: (response) => {
          const platformData = this.platforms.find(p => p.platform === platform);
          if (platformData) {
            platformData.is_connected = response.is_connected || false;
            platformData.account_name = response.account_name || null;
            platformData.account_username = response.account_username || null;
            platformData.profile_picture = response.profile_picture || null;
            platformData.expires_at = response.expires_at || null;
            platformData.is_token_expired = response.is_token_expired || false;
            platformData.needs_config = !response.is_connected;
          }
          resolve();
        },
        error: () => {
          const platformData = this.platforms.find(p => p.platform === platform);
          if (platformData) {
            platformData.is_connected = false;
            platformData.needs_config = true;
          }
          resolve();
        }
      });
    });
  }

  // ============================================================
  // CONNEXION
  // ============================================================

  connect(platform: string): void {
    const platformData = this.platforms.find(p => p.platform === platform);
    if (!platformData) return;

    platformData.is_connecting = true;
    this.clearMessages();

    const url = `${environment.apiUrl}/social/${platform}/connect`;
    this.http.get<any>(url).subscribe({
      next: (response) => {
        if (response.success && response.auth_url) {
          const width = 600;
          const height = 700;
          const left = (window.innerWidth - width) / 2;
          const top = (window.innerHeight - height) / 2;

          const popup = window.open(
            response.auth_url,
            `connect_${platform}`,
            `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,scrollbars=yes`
          );

          const checkInterval = setInterval(() => {
            this.loadPlatformStatus(platform).then(() => {
              const updatedPlatform = this.platforms.find(p => p.platform === platform);
              if (updatedPlatform?.is_connected) {
                clearInterval(checkInterval);
                updatedPlatform.is_connecting = false;
                this.setSuccessMessage(`Compte ${updatedPlatform.label} connecté avec succès !`);
                this.scrollToTop();
                if (popup && !popup.closed) {
                  popup.close();
                }
              }
            });
          }, 3000);

          setTimeout(() => {
            clearInterval(checkInterval);
            const p = this.platforms.find(p => p.platform === platform);
            if (p) {
              p.is_connecting = false;
            }
          }, 300000);
        } else {
          const p = this.platforms.find(p => p.platform === platform);
          if (p) {
            p.is_connecting = false;
          }
          this.setErrorMessage(response.message || 'Erreur lors de la connexion');
          this.scrollToTop();
        }
      },
      error: (error) => {
        const p = this.platforms.find(p => p.platform === platform);
        if (p) {
          p.is_connecting = false;
        }
        this.setErrorMessage(error.error?.message || 'Erreur lors de la connexion');
        this.scrollToTop();
      }
    });
  }

  // ============================================================
  // IMPORT DE TOKEN
  // ============================================================

  openImportTokenModal(platform: 'instagram' | 'tiktok'): void {
    this.importTokenPlatform = platform;
    this.accessToken = '';
    this.userId = '';
    this.username = '';
    this.isImporting = false;
    this.clearMessages();
    this.showTokenModal = true;
  }

  closeImportTokenModal(): void {
    this.showTokenModal = false;
    this.accessToken = '';
    this.userId = '';
    this.username = '';
    this.isImporting = false;
  }

  importToken(): void {
    // Validation des champs requis
    if (!this.accessToken || this.accessToken.trim() === '') {
      this.setErrorMessage('Veuillez entrer un token valide');
      return;
    }

    if (!this.userId || this.userId.trim() === '') {
      this.setErrorMessage('Veuillez entrer l\'ID du compte Instagram');
      return;
    }

    if (!this.username || this.username.trim() === '') {
      this.setErrorMessage('Veuillez entrer le nom d\'utilisateur Instagram');
      return;
    }

    this.isImporting = true;
    this.clearMessages();

    const platform = this.importTokenPlatform;

    if (platform === 'instagram') {
      const url = `${environment.apiUrl}/social/instagram/import-token`;
      this.http.post(url, {
        access_token: this.accessToken.trim(),
        user_id: this.userId.trim(),
        username: this.username.trim()
      }).subscribe({
        next: (response: any) => {
          this.isImporting = false;
          // Fermer le modal
          this.closeImportTokenModal();
          // Afficher le message de succès
          this.setSuccessMessage('Token Instagram importé avec succès !');
          // Recharger les statuts
          this.loadAllStatus();
          // Scroller en haut
          this.scrollToTop();
        },
        error: (error) => {
          this.isImporting = false;
          // Ne pas fermer le modal en cas d'erreur
          this.setErrorMessage(error.error?.message || 'Erreur lors de l\'import du token');
          this.scrollToTop();
        }
      });
    } else {
      this.isImporting = false;
      this.setErrorMessage('⚠️ L\'import de token TikTok n\'est pas encore disponible. Utilisez OAuth.');
      this.scrollToTop();
    }
  }

  // ============================================================
  // DÉCONNEXION
  // ============================================================

  openDisconnectModal(platform: string): void {
    this.platformToDisconnect = platform;
    this.isDisconnecting = false;
    this.showDisconnectModal = true;
  }

  closeDisconnectModal(): void {
    this.showDisconnectModal = false;
    this.platformToDisconnect = null;
    this.isDisconnecting = false;
  }

  disconnect(): void {
    if (!this.platformToDisconnect) return;

    const platform = this.platformToDisconnect;
    const platformData = this.platforms.find(p => p.platform === platform);
    const url = `${environment.apiUrl}/social/${platform}/disconnect`;

    this.isDisconnecting = true;
    this.clearMessages();

    this.http.post<any>(url, {}).subscribe({
      next: (response) => {
        this.isDisconnecting = false;
        // Fermer le modal
        this.closeDisconnectModal();
        // Afficher le message de succès
        this.setSuccessMessage(`Compte ${platform} déconnecté avec succès`);
        // Recharger les statuts
        this.loadAllStatus();
        // Scroller en haut
        this.scrollToTop();
        if (platformData) {
          platformData.is_connecting = false;
        }
      },
      error: (error) => {
        this.isDisconnecting = false;
        // Ne pas fermer le modal en cas d'erreur
        this.setErrorMessage(error.error?.message || 'Erreur lors de la déconnexion');
        this.scrollToTop();
        if (platformData) {
          platformData.is_connecting = false;
        }
      }
    });
  }

  // ============================================================
  // UTILITAIRES
  // ============================================================

  refresh(): void {
    this.loadAllStatus();
  }

  getPlatformLabel(platform: string): string {
    const p = this.platforms.find(p => p.platform === platform);
    return p?.label || platform;
  }

  getPlatformColor(platform: string): string {
    const p = this.platforms.find(p => p.platform === platform);
    return p?.color || '#6b7280';
  }

  getStatusLabel(platform: string): string {
    const p = this.platforms.find(p => p.platform === platform);
    if (!p) return 'Non configuré';
    if (p.is_connected && !p.is_token_expired) return 'Connecté';
    if (p.is_connected && p.is_token_expired) return 'Token expiré';
    return 'Non connecté';
  }

  getStatusClass(platform: string): string {
    const p = this.platforms.find(p => p.platform === platform);
    if (!p) return 'disconnected';
    if (p.is_connected && !p.is_token_expired) return 'connected';
    if (p.is_connected && p.is_token_expired) return 'expired';
    return 'disconnected';
  }

  isConnecting(platform: string): boolean {
    const p = this.platforms.find(p => p.platform === platform);
    return p?.is_connecting || false;
  }

  get greeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  get firstName(): string {
    return this.currentUser?.firstname ?? this.currentUser?.name ?? 'Utilisateur';
  }

  toggleSidebar(): void {
    this.mobileSidebarOpen = !this.mobileSidebarOpen;
  }
}