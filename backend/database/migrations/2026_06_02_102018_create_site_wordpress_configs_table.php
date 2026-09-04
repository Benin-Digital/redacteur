<?php
// database/migrations/2026_06_02_000002_create_site_wordpress_configs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_wordpress_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained('sites')->onDelete('cascade');
            $table->string('wp_url', 255);
            $table->string('wp_username', 100);
            $table->text('wp_app_password'); // Chiffré automatiquement
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_status')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->integer('use_count')->default(0);
            $table->json('metadata')->nullable(); // Informations supplémentaires
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('last_test_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_wordpress_configs');
    }
};