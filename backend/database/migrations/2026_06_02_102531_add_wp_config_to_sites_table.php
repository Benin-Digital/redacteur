<?php
// database/migrations/2026_06_02_000004_add_wp_config_to_sites_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('has_wp_config')->default(false);
            $table->timestamp('wp_config_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['has_wp_config', 'wp_config_updated_at']);
        });
    }
};