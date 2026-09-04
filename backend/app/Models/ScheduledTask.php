<?php
// app/Models/ScheduledTask.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'article_id',
        'media_id',
        'platform',
        'caption',
        'scheduled_at',
        'status',
        'metadata',
        'error_message',
        'processed_at'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec l'article
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Relation avec le média
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(UserMedia::class);
    }

    /**
     * Vérifier si c'est une tâche pour un article
     */
    public function isArticle(): bool
    {
        return $this->type === 'article';
    }

    /**
     * Vérifier si c'est une tâche pour un média
     */
    public function isMedia(): bool
    {
        return $this->type === 'media';
    }

    /**
     * Vérifier si la tâche est en attente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Vérifier si la tâche est terminée
     */
    public function isExecuted(): bool
    {
        return $this->status === 'executed';
    }

    /**
     * Marquer comme en traitement
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Marquer comme exécutée
     */
    public function markAsExecuted(array $result = null): void
    {
        $this->update([
            'status' => 'executed',
            'processed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], ['result' => $result])
        ]);
    }

    /**
     * Marquer comme échouée
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'processed_at' => now()
        ]);
    }

    /**
     * Annuler la tâche
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Scope pour les tâches en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope pour les tâches d'un type spécifique
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les tâches d'une plateforme
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }
}