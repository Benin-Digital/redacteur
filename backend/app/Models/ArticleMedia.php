<?php
// app/Models/ArticleMedia.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleMedia extends Model
{
    protected $fillable = [
        'article_id', 'user_id',
        'type', 'file_name', 'file_path', 'file_url', 'mime_type', 'file_size',
        'width', 'height', 'duration_seconds',
        'position', 'order_index',
        'provider', 'model_used',
        'prompt_used',
        'status',
        'is_integrated', 'is_hero',
        'api_response', 'metadata'
    ];

    protected $casts = [
        'api_response' => 'array',
        'metadata' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'is_integrated' => 'boolean',
        'is_hero' => 'boolean',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Marquer comme intégré dans l'article
     */
    public function markAsIntegrated(): void
    {
        $this->update([
            'is_integrated' => true,
            'status' => 'completed'
        ]);
    }

    /**
     * Obtenir le HTML pour l'intégration
     */
    public function getEmbedHtml(): string
    {
        if ($this->type === 'image') {
            return sprintf(
                '<img src="%s" alt="%s" width="%d" height="%d" />',
                $this->file_url ?? $this->file_path,
                $this->metadata['alt_text'] ?? $this->file_name ?? 'Image',
                $this->width ?? 800,
                $this->height ?? 600
            );
        }

        // Vidéo
        return sprintf(
            '<video controls width="%d" height="%d">
                <source src="%s" type="%s">
                Votre navigateur ne supporte pas la lecture vidéo.
            </video>',
            800, 450,
            $this->file_url ?? $this->file_path,
            $this->mime_type ?? 'video/mp4'
        );
    }
}