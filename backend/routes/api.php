<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\WordpressConfigController;
use App\Http\Controllers\LlmTestController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SocialConfigController;
use App\Http\Controllers\UserMediaController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Routes pour l'authentification et la gestion des utilisateurs
|--------------------------------------------------------------------------
*/

// ============================================================
// ROUTES PUBLIQUES
// ============================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// ============================================================
// ROUTES 2FA - Authentification à deux facteurs
// ============================================================
Route::post('/login/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
Route::post('/login/resend-code', [AuthController::class, 'resendTwoFactorCode']);

// Routes pour l'agent Python
Route::get('/agent/llm-config', [ApiKeyController::class, 'getActiveConfig']);

// ============================================================
// CALLBACKS OAuth — SANS auth (appelés par les plateformes externes)
// ============================================================
Route::get('/social/tiktok/callback', [SocialConfigController::class, 'tiktokCallback'])->name('tiktok.callback');
Route::get('/social/instagram/callback', [SocialConfigController::class, 'instagramCallback'])->name('instagram.callback');

// ============================================================
// ROUTES PROTÉGÉES (Authentification requise)
// ============================================================
Route::middleware(['auth:sanctum'])->group(function () {

    // ============================================================
    // Routes communes à tous les utilisateurs authentifiés
    // ============================================================
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/password/change', [AuthController::class, 'changePassword']);

    // ============================================================
    // ROUTES MANAGER (et admins)
    // ============================================================
    Route::middleware(['manager'])->group(function () {

        // ============================================================
        // 1. GESTION DES SITES
        // ============================================================
        Route::prefix('sites')->group(function () {
            Route::post('/analyze', [ManagerController::class, 'analyzeSite']);
            Route::get('/', [ManagerController::class, 'listSites']);
            Route::get('/{id}', [ManagerController::class, 'getSite']);
            Route::delete('/{id}', [ManagerController::class, 'deleteSite']);
            Route::get('/{id}/themes', [ManagerController::class, 'getSiteThemes']);
        });

        // ============================================================
        // 2. GESTION DES ARTICLES
        // ============================================================
        Route::prefix('articles')->group(function () {
            Route::post('/generate', [ManagerController::class, 'generateArticle']);
            Route::get('/', [ManagerController::class, 'listArticles']);
            Route::get('/scheduled', [ManagerController::class, 'scheduledArticles']);
            Route::get('/{id}', [ManagerController::class, 'getArticle']);
            Route::put('/{id}', [ManagerController::class, 'updateArticle']);
            Route::delete('/{id}', [ManagerController::class, 'deleteArticle']);
            Route::post('/{id}/publish', [ManagerController::class, 'publishArticle']);
            Route::post('/{id}/schedule', [ManagerController::class, 'scheduleArticle']);
            Route::delete('/{id}/schedule', [ManagerController::class, 'cancelSchedule']);
            Route::put('/{id}/schedule', [ManagerController::class, 'updateSchedule']);
        });

        // ============================================================
        // 3. MÉDIAS POUR ARTICLES (Images & Vidéos)
        // ============================================================
        Route::prefix('articles/{articleId}/media')->group(function () {
            Route::post('/images/generate', [MediaController::class, 'generateImages']);
            Route::post('/video/generate', [MediaController::class, 'generateVideo']);
            Route::get('/', [MediaController::class, 'getArticleMedia']);
            Route::post('/integrate', [MediaController::class, 'integrateMediaWithLlm']);
            Route::post('/{mediaId}/set-hero', [MediaController::class, 'setHeroImage']);
            Route::delete('/{mediaId}', [MediaController::class, 'deleteMedia']);
        });

        // LLM pour la génération de texte (liste des modèles disponibles)
        Route::get('llm/models/text', [ApiKeyController::class, 'getTextModels']);

        // ============================================================
        // 4. MÉDIAS INDÉPENDANTS (Génération, Upload, Bibliothèque)
        // ============================================================
        Route::prefix('media')->group(function () {
            // Génération
            Route::post('/generate-image', [UserMediaController::class, 'generateImage']);
            Route::post('/generate-video', [UserMediaController::class, 'generateVideo']);

            // Upload
            Route::post('/upload', [UserMediaController::class, 'upload']);
            Route::post('/upload-multiple', [UserMediaController::class, 'uploadMultiple']);

            // Bibliothèque
            Route::get('/library', [UserMediaController::class, 'library']);
            Route::get('/stats', [UserMediaController::class, 'stats']);
            Route::get('/active-providers', [UserMediaController::class, 'getActiveProviders']);

            Route::post('/{id}/schedule', [UserMediaController::class, 'schedulePublication']);

            // Tâches programmées
            Route::get('/scheduled-tasks', [UserMediaController::class, 'listScheduledTasks']);
            Route::put('/scheduled-tasks/{taskId}', [UserMediaController::class, 'updateScheduledTask']);
            Route::delete('/scheduled-tasks/{taskId}', [UserMediaController::class, 'deleteScheduledTask']);

            // CRUD
            Route::get('/{id}', [UserMediaController::class, 'show']);
            Route::delete('/{id}', [UserMediaController::class, 'delete']);
            Route::get('/{id}/download', [UserMediaController::class, 'download']);
        });

        // ============================================================
        // 5. RÉSEAUX SOCIAUX (Configuration + Publication)
        // ============================================================
        Route::prefix('social')->group(function () {

            // ============================================================
            // 5a. TIKTOK - UNIFIÉ DANS SocialConfigController
            // ============================================================
            Route::prefix('tiktok')->group(function () {
                // OAuth
                Route::get('/connect', [SocialConfigController::class, 'connectTikTok']);
                Route::get('/status', [SocialConfigController::class, 'tiktokStatus']);
                Route::post('/disconnect', [SocialConfigController::class, 'disconnectTikTok']);

                // Publication réelle
                Route::post('/publish', [SocialConfigController::class, 'publishToTikTok']);
                Route::get('/publish/{publishId}/status', [SocialConfigController::class, 'checkTikTokStatus']);

                Route::post('/import-credentials', [SocialConfigController::class, 'importTikTokCredentials']);
            });

            // ============================================================
            // 5b. INSTAGRAM - UNIFIÉ DANS SocialConfigController
            // ============================================================
            Route::prefix('instagram')->group(function () {
                // OAuth
                Route::get('/connect', [SocialConfigController::class, 'connectInstagram']);
                Route::get('/status', [SocialConfigController::class, 'instagramStatus']);
                Route::post('/disconnect', [SocialConfigController::class, 'disconnectInstagram']);

                // Publication réelle
                Route::post('/publish', [SocialConfigController::class, 'publishToInstagram']);

                // Nouvelle route pour importer un token
                Route::post('/import-token', [SocialConfigController::class, 'importInstagramToken']);
            });

            // ============================================================
            // 5c. PUBLICATION GÉNÉRIQUE - UNIFIÉ DANS SocialConfigController
            // ============================================================
            Route::prefix('publish')->group(function () {
                Route::post('/', [SocialConfigController::class, 'publish']);
                Route::get('/posts', [SocialConfigController::class, 'posts']);
                Route::get('/posts/{id}', [SocialConfigController::class, 'postStatus']);
            });
        });

        // ============================================================
        // 6. CONFIGURATION WORDPRESS
        // ============================================================
        Route::prefix('sites')->group(function () {
            Route::get('/wp-configs', [WordpressConfigController::class, 'listAllWithConfig']);
            Route::get('{siteId}/wp-config', [WordpressConfigController::class, 'show']);
            Route::post('{siteId}/wp-config', [WordpressConfigController::class, 'storeOrUpdate']);
            Route::delete('{siteId}/wp-config', [WordpressConfigController::class, 'destroy']);
            Route::post('{siteId}/wp-config/test', [WordpressConfigController::class, 'test']);
        });
    });

    // ============================================================
    // ROUTES ADMIN
    // ============================================================
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        // Gestion des managers
        Route::get('/managers', [AdminController::class, 'listManagers']);
        Route::post('/managers', [AdminController::class, 'createManager']);
        Route::get('/managers/{id}', [AdminController::class, 'getManager']);
        Route::put('/managers/{id}', [AdminController::class, 'updateManager']);
        Route::delete('/managers/{id}', [AdminController::class, 'deleteManager']);
        Route::patch('/managers/{id}/toggle-status', [AdminController::class, 'toggleManagerStatus']);

        // Supervision des sites
        Route::get('/sites', [AdminController::class, 'listAllSites']);
        Route::delete('/sites/{id}', [AdminController::class, 'deleteAnySite']);

        // Supervision des articles
        Route::get('/articles', [AdminController::class, 'listAllArticles']);
        Route::delete('/articles/{id}', [AdminController::class, 'deleteAnyArticle']);

        // Gestion des clés API LLM
        Route::apiResource('api-keys', ApiKeyController::class);
        Route::post('api-keys/{id}/test', [ApiKeyController::class, 'test']);
        Route::get('api-keys/active/config', [ApiKeyController::class, 'getActive']);

        // Logs
        Route::get('/logs', [AdminController::class, 'getLogs']);
        Route::get('/logs/user/{userId}', [AdminController::class, 'getUserLogs']);

        // Statistiques
        Route::get('/stats', [AdminController::class, 'getStats']);
    });
});
