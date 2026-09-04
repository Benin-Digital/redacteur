<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'url',
        'description',
        'contexte_json',
        'analyzed_at',
    ];

    protected $casts = [
        'contexte_json' => 'array',
        'analyzed_at' => 'datetime',
    ];

    // ============================================================
    // Relations
    // ============================================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function wordpressConfig()
    {
        return $this->hasOne(SiteWordpressConfig::class);
    }
}
