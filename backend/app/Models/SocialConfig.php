<?php
// app/Models/SocialConfig.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SocialConfig extends Model
{
    use HasFactory;

    protected $table = 'social_configs';

    protected $fillable = [
        'user_id',
        'platform', // tiktok, instagram, facebook, twitter
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'open_id',           // Ajouté pour TikTok
        'account_name',
        'account_id',
        'account_username',  // Ajouté pour TikTok/Instagram
        'profile_picture_url',
        'is_active',
        'is_connected',
        'metadata',
        'redirect_uri',      // Ajouté
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_connected' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token'
    ];

    // ============================================================
    // MUTATEURS POUR LE CHIFFREMENT (VERSION UNIFIÉE)
    // ============================================================

    /**
     * Chiffrer le client_secret avant de le sauvegarder
     */
    public function setClientSecretAttribute($value): void
    {
        if ($value) {
            $this->attributes['client_secret'] = Crypt::encryptString($value);
        } else {
            $this->attributes['client_secret'] = null;
        }
    }

    /**
     * Déchiffrer le client_secret pour l'utiliser
     */
    public function getClientSecretAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Chiffrer le access_token avant de le sauvegarder
     */
    public function setAccessTokenAttribute($value): void
    {
        if ($value) {
            $this->attributes['access_token'] = Crypt::encryptString($value);
        } else {
            $this->attributes['access_token'] = null;
        }
    }

    /**
     * Déchiffrer le access_token pour l'utiliser
     */
    public function getAccessTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // Si le token n'est pas chiffré (anciennes données), le retourner tel quel
            return $value;
        }
    }

    /**
     * Chiffrer le refresh_token avant de le sauvegarder
     */
    public function setRefreshTokenAttribute($value): void
    {
        if ($value) {
            $this->attributes['refresh_token'] = Crypt::encryptString($value);
        } else {
            $this->attributes['refresh_token'] = null;
        }
    }

    /**
     * Déchiffrer le refresh_token pour l'utiliser
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ============================================================
    // RELATIONS
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts()
    {
        return $this->hasMany(SocialPost::class);
    }

    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class);
    }

    // ============================================================
    // MÉTHODES UTILITAIRES
    // ============================================================

    /**
     * Obtenir le libellé de la plateforme
     */
    public function getPlatformLabel(): string
    {
        return match ($this->platform) {
            'tiktok' => 'TikTok',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'twitter' => 'Twitter',
            default => ucfirst($this->platform),
        };
    }

    /**
     * Obtenir l'icône de la plateforme
     */
    public function getPlatformIcon(): string
    {
        return match ($this->platform) {
            'tiktok' => 'bi-tiktok',
            'instagram' => 'bi-instagram',
            'facebook' => 'bi-facebook',
            'twitter' => 'bi-twitter',
            default => 'bi-globe',
        };
    }

    /**
     * Obtenir la couleur de la plateforme
     */
    public function getPlatformColor(): string
    {
        return match ($this->platform) {
            'tiktok' => '#000000',
            'instagram' => '#E4405F',
            'facebook' => '#1877F2',
            'twitter' => '#1DA1F2',
            default => '#6b7280',
        };
    }

    /**
     * Vérifier si le token est expiré
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    /**
     * Obtenir le token masqué pour l'affichage
     */
    public function getMaskedToken(): string
    {
        $token = $this->access_token;
        if (!$token) {
            return '••••••••';
        }
        $length = strlen($token);
        if ($length <= 12) {
            return str_repeat('•', $length);
        }
        return substr($token, 0, 6) . str_repeat('•', $length - 12) . substr($token, -6);
    }

    /**
     * Vérifier si le compte est correctement configuré
     */
    public function isFullyConfigured(): bool
    {
        return $this->is_connected && $this->client_id && $this->access_token;
    }

    /**
     * Obtenir le token pour l'API (déchiffré automatiquement)
     */
    public function getDecryptedAccessToken(): ?string
    {
        return $this->access_token;
    }

    /**
     * Obtenir le refresh_token pour l'API (déchiffré automatiquement)
     */
    public function getDecryptedRefreshToken(): ?string
    {
        return $this->refresh_token;
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /**
     * Scope pour les comptes connectés
     */
    public function scopeConnected($query)
    {
        return $query->where('is_connected', true);
    }

    /**
     * Scope pour les comptes actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour une plateforme spécifique
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope pour un utilisateur spécifique
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ============================================================
    // MÉTHODES STATIQUES
    // ============================================================

    /**
     * Obtenir la configuration d'une plateforme pour un utilisateur
     */
    public static function getUserPlatformConfig(int $userId, string $platform): ?self
    {
        return self::where('user_id', $userId)
            ->where('platform', $platform)
            ->first();
    }

    /**
     * Obtenir les plateformes connectées d'un utilisateur
     */
    public static function getUserConnectedPlatforms(int $userId): array
    {
        return self::where('user_id', $userId)
            ->where('is_connected', true)
            ->pluck('platform')
            ->toArray();
    }

    /**
     * Obtenir les plateformes disponibles avec leur statut
     */
    public static function getPlatformsStatus(int $userId): array
    {
        $platforms = ['tiktok', 'instagram', 'facebook', 'twitter'];
        $result = [];

        foreach ($platforms as $platform) {
            $config = self::where('user_id', $userId)
                ->where('platform', $platform)
                ->first();

            $result[$platform] = [
                'platform' => $platform,
                'label' => match($platform) {
                    'tiktok' => 'TikTok',
                    'instagram' => 'Instagram',
                    'facebook' => 'Facebook',
                    'twitter' => 'Twitter',
                },
                'icon' => match($platform) {
                    'tiktok' => 'bi-tiktok',
                    'instagram' => 'bi-instagram',
                    'facebook' => 'bi-facebook',
                    'twitter' => 'bi-twitter',
                },
                'is_connected' => $config ? $config->is_connected : false,
                'account_name' => $config ? $config->account_name : null,
                'account_username' => $config ? $config->account_username : null,
                'profile_picture' => $config ? $config->profile_picture_url : null,
                'expires_at' => $config ? $config->token_expires_at?->toIso8601String() : null,
                'is_token_expired' => $config ? $config->isTokenExpired() : false,
                'config' => $config,
                'needs_config' => !$config,
            ];
        }

        return $result;
    }

    /**
     * Créer ou mettre à jour la configuration d'une plateforme
     */
    public static function updateOrCreateConfig(
        int $userId,
        string $platform,
        array $data
    ): self {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'platform' => $platform,
            ],
            $data
        );
    }

    /**
     * Déconnecter une plateforme
     */
    public static function disconnectPlatform(int $userId, string $platform): bool
    {
        $config = self::where('user_id', $userId)
            ->where('platform', $platform)
            ->first();

        if (!$config) {
            return false;
        }

        return $config->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'is_connected' => false,
        ]);
    }
}