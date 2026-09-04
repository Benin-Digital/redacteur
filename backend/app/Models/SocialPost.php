<?php
// app/Models/SocialPost.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    use HasFactory;

    protected $table = 'social_posts';

    protected $fillable = [
        'user_id',
        'media_id',
        'social_config_id',
        'platform',
        'caption',
        'post_id',
        'post_url',
        'status',
        'scheduled_at',
        'published_at',
        'response_data',
        'error_message'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'response_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le média
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(UserMedia::class);
    }

    /**
     * Relation avec la configuration sociale
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(SocialConfig::class, 'social_config_id');
    }

    /**
     * Vérifier si la publication est en attente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Vérifier si la publication est programmée
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Vérifier si la publication est publiée
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Vérifier si la publication a échoué
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Marquer comme publiée
     */
    public function markAsPublished(string $postId, string $postUrl = null): void
    {
        $this->update([
            'status' => 'published',
            'post_id' => $postId,
            'post_url' => $postUrl,
            'published_at' => now()
        ]);
    }

    /**
     * Marquer comme échouée
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Marquer comme programmée
     */
    public function markAsScheduled(string $scheduledAt): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt
        ]);
    }

    /**
     * Obtenir le statut en français
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'scheduled' => 'Programmée',
            'published' => 'Publiée',
            'failed' => 'Échouée',
            default => $this->status,
        };
    }

    /**
     * Obtenir la couleur du statut
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => '#f59e0b',
            'scheduled' => '#3b82f6',
            'published' => '#28b188',
            'failed' => '#dc2626',
            default => '#6b7280',
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
     * Obtenir le nom affichable de la plateforme
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
     * Scope pour les publications en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope pour les publications programmées
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope pour les publications publiées
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope pour les publications échouées
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope pour une plateforme spécifique
     */
    public function scopeOnPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope pour les publications programmées à venir
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc');
    }

    /**
     * Scope pour les publications qui doivent être exécutées
     */
    public function scopeDueForExecution($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }
}