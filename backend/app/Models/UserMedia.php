<?php
// app/Models/UserMedia.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserMedia extends Model
{
    use HasFactory;

    protected $table = 'user_media';

    protected $fillable = [
        'user_id',
        'type', // image, video, audio
        'title',
        'prompt_used',
        'file_name',
        'file_url',
        'file_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'duration_seconds',
        'provider',
        'model_used',
        'status',
        'published_to_tiktok',
        'published_to_instagram',
        'published_to_facebook',
        'published_to_twitter',
        'published_at',
        'publication_results',
        'metadata'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'published_to_tiktok' => 'boolean',
        'published_to_instagram' => 'boolean',
        'published_to_facebook' => 'boolean',
        'published_to_twitter' => 'boolean',
        'publication_results' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
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
     * Publications sociales liées à ce média
     */
    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class);
    }

    /**
     * Vérifier si le média a été publié sur une plateforme
     */
    public function isPublishedOn(string $platform): bool
    {
        return match ($platform) {
            'tiktok' => $this->published_to_tiktok,
            'instagram' => $this->published_to_instagram,
            'facebook' => $this->published_to_facebook,
            'twitter' => $this->published_to_twitter,
            default => false,
        };
    }

    /**
     * Marquer comme publié sur une plateforme
     */
    public function markAsPublishedOn(string $platform, string $postId, string $postUrl = null): void
    {
        $field = "published_to_{$platform}";
        $results = $this->publication_results ?? [];
        
        $results[$platform] = [
            'post_id' => $postId,
            'post_url' => $postUrl,
            'published_at' => now()->toIso8601String()
        ];
        
        $this->update([
            $field => true,
            'published_at' => now(),
            'publication_results' => $results
        ]);
    }

    /**
     * Obtenir l'URL de partage pour un réseau social
     */
    public function getSocialShareUrl(string $platform): ?string
    {
        $results = $this->publication_results ?? [];
        return $results[$platform]['post_url'] ?? null;
    }

    /**
     * Obtenir l'icône selon le type de média
     */
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            'image' => 'bi-image',
            'video' => 'bi-film',
            'audio' => 'bi-music-note',
            default => 'bi-file',
        };
    }

    /**
     * Obtenir la couleur selon le type de média
     */
    public function getTypeColor(): string
    {
        return match ($this->type) {
            'image' => '#28b188',
            'video' => '#7c3aed',
            'audio' => '#f59e0b',
            default => '#6b7280',
        };
    }

    /**
     * Formatage de la taille du fichier
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = $this->file_size;
        
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Scope pour les images
     */
    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    /**
     * Scope pour les vidéos
     */
    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    /**
     * Scope pour les médias publiés sur une plateforme
     */
    public function scopePublishedOn($query, string $platform)
    {
        $field = "published_to_{$platform}";
        return $query->where($field, true);
    }

    /**
     * Scope pour les médias non publiés sur une plateforme
     */
    public function scopeNotPublishedOn($query, string $platform)
    {
        $field = "published_to_{$platform}";
        return $query->where($field, false);
    }
}