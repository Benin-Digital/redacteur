<?php
// database/migrations/2026_06_xx_create_social_configs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->enum('platform', ['tiktok', 'instagram', 'facebook', 'twitter']);
            
            // Clés API
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->string('access_token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            
            // Informations du compte
            $table->string('account_name')->nullable();
            $table->string('account_id')->nullable();
            $table->string('profile_picture_url')->nullable();
            
            // Statut
            $table->boolean('is_active')->default(false);
            $table->boolean('is_connected')->default(false);
            
            // Métadonnées
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            $table->unique(['user_id', 'platform']);
            $table->index(['user_id', 'platform', 'is_connected']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_configs');
    }
};