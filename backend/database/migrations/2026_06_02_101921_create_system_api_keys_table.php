<?php
// database/migrations/2026_06_02_000001_create_system_api_keys_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50); // mistral, openai, anthropic, deepseek, gemini
            $table->text('api_key'); // Chiffré automatiquement
            $table->string('model_name', 100)->nullable(); // Modèle par défaut pour ce provider
            $table->boolean('is_active')->default(false); // Un seul provider actif à la fois
            $table->integer('priority')->default(0); // Ordre de fallback (si actif)
            $table->json('config')->nullable(); // Configuration supplémentaire (température, max_tokens, etc.)
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Index pour recherche rapide
            $table->index('is_active');
            $table->index('provider');
            $table->unique(['provider', 'is_active']); // Un seul actif par provider
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_api_keys');
    }
};