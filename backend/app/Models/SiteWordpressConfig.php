<?php
// app/Models/SiteWordpressConfig.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SiteWordpressConfig extends Model
{
    use HasFactory;

    protected $table = 'site_wordpress_configs';

    protected $fillable = [
        'site_id',
        'wp_url',
        'wp_username',
        'wp_app_password',
        'is_active',
        'last_test_at',
        'last_test_status',
        'last_used_at',
        'use_count',
        'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_test_status' => 'boolean',
        'last_test_at' => 'datetime',
        'last_used_at' => 'datetime',
        'use_count' => 'integer',
        'metadata' => 'array'
    ];

    // ============================================================
    // Chiffrement automatique du mot de passe
    // ============================================================
    public function setWpAppPasswordAttribute($value)
    {
        $this->attributes['wp_app_password'] = Crypt::encryptString($value);
    }

    public function getWpAppPasswordAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ============================================================
    // Relations
    // ============================================================
    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    // ============================================================
    // Méthodes
    // ============================================================
    public function incrementUseCount(): void
    {
        $this->increment('use_count');
        $this->update(['last_used_at' => now()]);
    }

    public function getMaskedPassword(): string
    {
        $password = $this->wp_app_password;
        if (strlen($password) <= 8) {
            return str_repeat('•', strlen($password));
        }
        return substr($password, 0, 4) . str_repeat('•', strlen($password) - 8) . substr($password, -4);
    }
}