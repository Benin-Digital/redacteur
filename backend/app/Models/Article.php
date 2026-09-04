<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'user_id',
        'titre',
        'meta_desc',
        'slug',
        'contenu_md',
        'contenu_html',
        'categorie',
        'tags',
        'mot_cle_principal',
        'statut',
        'programme_le',
        'publie_le',
        'wp_url',
        'wp_post_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'programme_le' => 'datetime',
        'publie_le' => 'datetime',
    ];

    // ============================================================
    // Accesseurs
    // ============================================================

    /**
     * URL absolue de l'article (si publié sur WordPress)
     */
    public function getUrlAttribute(): ?string
    {
        return $this->wp_url;
    }

    // ============================================================
    // Relations
    // ============================================================

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledTask()
    {
        return $this->hasOne(ScheduledTask::class);
    }
}