<?php
// database/migrations/2026_06_xx_create_user_media_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->enum('type', ['image', 'video', 'audio']);
            $table->string('title')->nullable();
            $table->text('prompt_used')->nullable();
            
            // Fichier
            $table->string('file_name');
            $table->text('file_url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            
            // Métadonnées
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            // Provider
            $table->string('provider')->nullable();
            $table->string('model_used')->nullable();
            
            // Statut
            $table->enum('status', ['pending', 'generating', 'completed', 'failed'])->default('pending');
            
            // Publication sociale
            $table->boolean('published_to_tiktok')->default(false);
            $table->boolean('published_to_instagram')->default(false);
            $table->boolean('published_to_facebook')->default(false);
            $table->boolean('published_to_twitter')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('publication_results')->nullable();
            
            // Métadonnées
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Index
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_media');
    }
};