<?php
// database/migrations/2026_06_xx_create_social_posts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('media_id')->constrained('user_media')->onDelete('cascade');
            $table->foreignId('social_config_id')->constrained()->onDelete('cascade');
            
            $table->enum('platform', ['tiktok', 'instagram', 'facebook', 'twitter']);
            $table->text('caption')->nullable();
            
            // Résultat de la publication
            $table->string('post_id')->nullable();
            $table->string('post_url')->nullable();
            $table->enum('status', ['pending', 'scheduled', 'published', 'failed'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Index
            $table->index(['user_id', 'platform']);
            $table->index(['user_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};