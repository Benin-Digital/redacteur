<?php
// database/migrations/2026_06_xx_update_social_configs_table_for_tiktok.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_configs', function (Blueprint $table) {
            //  open_id pour TikTok
            if (!Schema::hasColumn('social_configs', 'open_id')) {
                $table->string('open_id')->nullable()->after('refresh_token')->comment('TikTok user ID');
            }

            //  account_username
            if (!Schema::hasColumn('social_configs', 'account_username')) {
                $table->string('account_username')->nullable()->after('account_id');
            }

            // Modifier is_active pour avoir une valeur par défaut à true
            if (Schema::hasColumn('social_configs', 'is_active')) {
                $table->boolean('is_active')->default(true)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_configs', function (Blueprint $table) {
            if (Schema::hasColumn('social_configs', 'open_id')) {
                $table->dropColumn('open_id');
            }

            if (Schema::hasColumn('social_configs', 'account_username')) {
                $table->dropColumn('account_username');
            }

            // Revenir à la valeur par défaut précédente
            if (Schema::hasColumn('social_configs', 'is_active')) {
                $table->boolean('is_active')->default(false)->change();
            }
        });
    }
};