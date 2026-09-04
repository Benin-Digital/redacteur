<?php
// database/migrations/xxxx_xx_xx_create_article_media_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Table des médias générés pour un article
        Schema::create('article_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Type de média
            $table->enum('type', ['image', 'video']);
            
            // Métadonnées du média
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();      // Chemin local ou URL
            $table->string('file_url')->nullable();       // URL publique
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();     // En bytes
            
            // Pour les images
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // Pour les vidéos
            $table->integer('duration_seconds')->nullable();
            
            // Position dans l'article
            $table->enum('position', ['hero', 'inline', 'end'])->default('inline');
            $table->integer('order_index')->default(0);
            
            // Généré par quel provider
            $table->string('provider')->nullable();       // openai, google, elevenlabs, etc.
            $table->string('model_used')->nullable();     // dall-e-3, veo-3.1, etc.
            
            // Prompt utilisé
            $table->text('prompt_used')->nullable();
            
            // Statut
            $table->enum('status', ['pending', 'generating', 'completed', 'failed'])->default('pending');
            
            // Intégration dans l'article
            $table->boolean('is_integrated')->default(false);
            $table->boolean('is_hero')->default(false);   // Image principale
            
            // Stockage des données brutes de l'API
            $table->json('api_response')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Index
            $table->index(['article_id', 'type']);
            $table->index(['user_id', 'status']);
        });

        // Table des prompts utilisés pour les médias (historique)
        Schema::create('media_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->enum('media_type', ['image', 'video']);
            $table->text('original_prompt');
            $table->text('optimized_prompt')->nullable();  // Prompt optimisé par l'IA
            
            // Options de génération
            $table->string('aspect_ratio')->nullable();    // 16:9, 9:16, 1:1
            $table->string('resolution')->nullable();      // 720p, 1080p, 4k
            $table->string('style')->nullable();           // Cinematic, modern, etc.
            $table->integer('count')->default(1);          // Nombre demandé
            
            // Résultats
            $table->json('results')->nullable();           // IDs des médias générés
            $table->boolean('is_success')->default(false);
            
            $table->timestamps();
        });

        //  des colonnes à la table articles pour les médias
        Schema::table('articles', function (Blueprint $table) {
            $table->string('hero_image_url')->nullable();
            $table->string('hero_image_id')->nullable();   // ID du média dans article_media
            $table->boolean('has_media')->default(false);
            $table->json('media_metadata')->nullable();    // Cache des métadonnées
        });
    }

    public function down()
    {
        Schema::dropIfExists('media_prompts');
        Schema::dropIfExists('article_media');
        
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['hero_image_url', 'hero_image_id', 'has_media', 'media_metadata']);
        });
    }
};