import { Routes } from '@angular/router';
import { AuthGuard } from './core/guards/auth.guard';
import { AdminGuard } from './core/guards/admin.guard';
import { ManagerGuard } from './core/guards/manager.guard';

// Pages publiques
import { AccueilComponent } from './pages/accueil/accueil.component';
import { Page404Component } from './pages/page404/page404.component';

// Auth
import { ConnexionComponent } from './pages/auth/connexion/connexion.component';
import { MotDePasseOublieComponent } from './pages/auth/mot-de-passe-oublie/mot-de-passe-oublie.component';
import { NouveauMotDePasseComponent } from './pages/auth/nouveau-mot-de-passe/nouveau-mot-de-passe.component';

// Manager (rédacteur)
import { ManagerDashboardComponent } from './pages/manager/dashboard/dashboard.component';
import { ManagerParametresComponent } from './pages/manager/parametres/parametres.component';
import { ManagerRedactionComponent } from './pages/manager/redaction/redaction.component';
import { ManagerSitesComponent } from './pages/manager/sites/sites.component';
import { ManagerArticlesComponent } from './pages/manager/articles/articles.component';

// Admin
import { AdminDashboardComponent } from './pages/admin/dashboard/dashboard.component';
import { AdminParametresComponent } from './pages/admin/parametres/parametres.component';
import { AdminStatsComponent } from './pages/admin/stats/stats.component';
import { AdminLogsComponent } from './pages/admin/logs/logs.component';
import { AdminArticlesComponent } from './pages/admin/articles/articles.component';
import { AdminUtilisateursComponent } from './pages/admin/utilisateurs/utilisateurs.component';
import { AdminApiKeysComponent } from './pages/admin/admin-api-keys/admin-api-keys.component';
import { ManagerWpConfigComponent } from './pages/manager/manager-wp-config/manager-wp-config.component';

// Documentation
import { MotDePasseApplicationComponent } from './pages/docs/mot-de-passe-application/mot-de-passe-application.component';
import { SocialConfigComponent } from './pages/manager/social-config/social-config.component';
import { LibraryComponent } from './pages/manager/media/library/library.component';
import { GenerateComponent } from './pages/manager/media/generate/generate.component';
import { TiktokCallbackComponent } from './pages/manager/social-config/tiktok-callback/tiktok-callback.component';
import { VerificationComponent } from './pages/auth/verification/verification.component';

export const routes: Routes = [
    // ============================================================
    // Routes publiques (pas d'authentification)
    // ============================================================
    { path: '', component: AccueilComponent },
    { path: 'connexion', component: ConnexionComponent },
    { path: 'mot-de-passe-oublie', component: MotDePasseOublieComponent },
    { path: 'reset-password', component: NouveauMotDePasseComponent },
    { path: 'verification', component: VerificationComponent },

    // ============================================================
    // Routes publiques de documentation (pas d'authentification)
    // ============================================================
    { path: 'docs/mot-de-passe-application-wordpress', component: MotDePasseApplicationComponent },

    // ============================================================
    // Routes Manager (rédacteur) - authentification + rôle manager
    // ============================================================
    {
        path: 'manager',
        canActivate: [AuthGuard, ManagerGuard],
        children: [
            // Routes existantes
            { path: 'dashboard', component: ManagerDashboardComponent },
            { path: 'parametres', component: ManagerParametresComponent },
            { path: 'redaction', component: ManagerRedactionComponent },
            { path: 'sites', component: ManagerSitesComponent },
            { path: 'articles', component: ManagerArticlesComponent },
            { path: 'wp-config', component: ManagerWpConfigComponent },

            // ============================================================
            // ROUTES MÉDIAS - Génération et bibliothèque
            // ============================================================
            {
                path: 'media/generate',
                component: GenerateComponent,
                data: { title: 'Générer des médias' }
            },
            {
                path: 'media/library',
                component: LibraryComponent,
                data: { title: 'Ma bibliothèque de médias' }
            },
            {
                path: 'media',
                redirectTo: 'media/library',
                pathMatch: 'full'
            },

            // ============================================================
            // ROUTES RÉSEAUX SOCIAUX - Configuration
            // ============================================================
            {
                path: 'social-config',
                component: SocialConfigComponent,
                data: { title: 'Configuration des réseaux sociaux' }
            },

            {
                path: 'social/tiktok',
                component: TiktokCallbackComponent,
                data: { title: 'Connexion TikTok' }
            },

            // Route par défaut
            { path: '', redirectTo: 'dashboard', pathMatch: 'full' }
        ]
    },

    // ============================================================
    // Routes Admin (super admin) - authentification + rôle admin
    // ============================================================
    {
        path: 'admin',
        canActivate: [AuthGuard, AdminGuard],
        children: [
            { path: 'dashboard', component: AdminDashboardComponent },
            { path: 'parametres', component: AdminParametresComponent },
            { path: 'stats', component: AdminStatsComponent },
            { path: 'logs', component: AdminLogsComponent },
            { path: 'articles', component: AdminArticlesComponent },
            { path: 'utilisateurs', component: AdminUtilisateursComponent },
            { path: 'api-keys', component: AdminApiKeysComponent },
            { path: '', redirectTo: 'dashboard', pathMatch: 'full' }
        ]
    },

    // ============================================================
    // Page 404
    // ============================================================
    { path: '**', component: Page404Component }
];