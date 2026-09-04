<?php
// app/Models/User.php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'firstname',
        'email',
        'password',
        'role',
        'is_active',
        'is_approved',
        'approved_at',
        'approved_by',
        'last_login_at',
        'password_changed_at',
        'two_factor_code',
        'two_factor_expires_at',
        'two_factor_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code', // Ne jamais exposer le code 2FA
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    // ============================================================
    // Accesseurs
    // ============================================================

    /**
     * Retourne le nom complet (prénom + nom)
     */
    public function getFullNameAttribute(): string
    {
        return $this->firstname ? "{$this->firstname} {$this->name}" : $this->name;
    }

    // ============================================================
    // Vérifications de rôle
    // ============================================================

    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifie si l'utilisateur est manager (rédacteur)
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Vérifie si l'utilisateur est documentaliste (user)
     */
    public function isDocumentaliste(): bool
    {
        return $this->role === 'user';
    }

    // ============================================================
    // Méthodes 2FA
    // ============================================================

    /**
     * Génère un code 2FA à 6 chiffres pour l'utilisateur
     */
    public function generateTwoFactorCode(): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
            'two_factor_verified_at' => null,
        ]);

        return $code;
    }

    /**
     * Vérifie si le code 2FA est valide
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        // Vérifier que le code correspond
        if ($this->two_factor_code !== $code) {
            return false;
        }

        // Vérifier que le code n'est pas expiré
        if (!$this->two_factor_expires_at || now()->gt($this->two_factor_expires_at)) {
            return false;
        }

        // Le code est valide - on le marque comme vérifié
        $this->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'two_factor_verified_at' => now(),
        ]);

        return true;
    }

    /**
     * Vérifie si le code 2FA est expiré
     */
    public function isTwoFactorCodeExpired(): bool
    {
        if (!$this->two_factor_expires_at) {
            return true;
        }

        return now()->gt($this->two_factor_expires_at);
    }

    /**
     * Efface le code 2FA (sans le vérifier)
     */
    public function clearTwoFactorCode(): void
    {
        $this->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);
    }

    /**
     * Vérifie si la 2FA est requise pour cet utilisateur
     */
    public function requiresTwoFactor(): bool
    {
        // Seuls les administrateurs nécessitent la 2FA
        return $this->isAdmin();
    }

    // ============================================================
    // Relations
    // ============================================================

    /**
     * Sites analysés par l'utilisateur
     */
    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Articles générés par l'utilisateur
     */
    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Logs des actions de l'utilisateur
     */
    public function logs()
    {
        return $this->hasMany(Log::class);
    }

    /**
     * Utilisateur qui a approuvé ce compte
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}