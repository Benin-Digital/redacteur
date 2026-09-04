<?php
// database/migrations/2026_06_02_000003_create_api_usage_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('site_id')->nullable()->constrained('sites')->onDelete('set null');
            $table->string('action', 50); // 'analyze', 'generate', 'publish'
            $table->string('provider', 50)->nullable(); // mistral, openai, etc.
            $table->string('model', 100)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('status', 20); // 'success', 'error', 'pending'
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'status']);
            $table->index('provider');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};