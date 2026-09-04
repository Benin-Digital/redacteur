<?php
// database/migrations/2026_06_29_085404_increase_social_configs_column_lengths.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vérifier si la table existe
        if (!Schema::hasTable('social_configs')) {
            return;
        }

        Schema::table('social_configs', function (Blueprint $table) {
            // Modifier client_secret en TEXT (permet d'accepter le chiffrement)
            if (Schema::hasColumn('social_configs', 'client_secret')) {
                $table->text('client_secret')->nullable()->change();
            }

            // Modifier access_token en TEXT
            if (Schema::hasColumn('social_configs', 'access_token')) {
                $table->text('access_token')->nullable()->change();
            }

            // Modifier refresh_token en TEXT
            if (Schema::hasColumn('social_configs', 'refresh_token')) {
                $table->text('refresh_token')->nullable()->change();
            }

            // Augmenter les autres colonnes
            if (Schema::hasColumn('social_configs', 'client_id')) {
                $table->string('client_id', 255)->nullable()->change();
            }

            if (Schema::hasColumn('social_configs', 'redirect_uri')) {
                $table->string('redirect_uri', 500)->nullable()->change();
            }

            if (Schema::hasColumn('social_configs', 'open_id')) {
                $table->string('open_id', 255)->nullable()->change();
            }

            if (Schema::hasColumn('social_configs', 'account_id')) {
                $table->string('account_id', 255)->nullable()->change();
            }

            if (Schema::hasColumn('social_configs', 'account_name')) {
                $table->string('account_name', 255)->nullable()->change();
            }

            if (Schema::hasColumn('social_configs', 'account_username')) {
                $table->string('account_username', 255)->nullable()->change();
            }

            if (Schema::hasColumn('social_configs', 'profile_picture_url')) {
                $table->string('profile_picture_url', 500)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Rollback non nécessaire car les données sont déjà en place
    }
};