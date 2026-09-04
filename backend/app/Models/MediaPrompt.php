<?php
// app/Models/MediaPrompt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaPrompt extends Model
{
    protected $fillable = [
        'article_id', 'user_id',
        'media_type', 'original_prompt', 'optimized_prompt',
        'aspect_ratio', 'resolution', 'style', 'count',
        'results', 'is_success'
    ];

    protected $casts = [
        'results' => 'array',
        'is_success' => 'boolean'
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}