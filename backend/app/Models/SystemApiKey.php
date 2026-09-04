<?php
// app/Models/SystemApiKey.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemApiKey extends Model
{
    use HasFactory;

    protected $table = 'system_api_keys';

    protected $fillable = [
        'provider',
        'api_key',
        'model_name',
        'capabilities',
        'is_active',
        'priority',
        'config',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'config' => 'array',
        'capabilities' => 'array'
    ];

    protected $hidden = ['api_key'];

    // ============================================================
    // Chiffrement automatique de la clé API
    // ============================================================
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    public function getApiKeyAttribute($value)
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
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Scopes
    // ============================================================
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeWithCapability($query, $capability)
    {
        return $query->whereJsonContains('capabilities', $capability);
    }

    // ============================================================
    // Méthodes
    // ============================================================
    public function getProviderDisplayName(): string
    {
        $names = [
            'mistral' => 'Mistral AI',
            'openai' => 'OpenAI (ChatGPT)',
            'anthropic' => 'Anthropic (Claude)',
            'deepseek' => 'DeepSeek',
            'gemini' => 'Google Gemini',
            'google' => 'Google (Gemini/Imagen/Veo)',
            'elevenlabs' => 'ElevenLabs',
            'replicate' => 'Replicate',
            'stability' => 'Stability AI',
            'runway' => 'Runway',
            'pika' => 'Pika Labs',
            'synthesia' => 'Synthesia'
        ];
        return $names[$this->provider] ?? ucfirst($this->provider);
    }

    public function getCapabilitiesLabels(): array
    {
        $labels = [
            'text_generation' => 'Génération de texte',
            'image_generation' => 'Génération d\'images',
            'video_generation' => 'Génération de vidéos',
            'audio_generation' => 'Génération audio'
        ];
        
        return array_map(function($cap) use ($labels) {
            return $labels[$cap] ?? $cap;
        }, $this->capabilities ?? []);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? []);
    }

    public static function getActiveForCapability(string $capability): ?self
    {
        return self::where('is_active', true)
            ->whereJsonContains('capabilities', $capability)
            ->first();
    }
}