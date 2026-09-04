<?php
// database/migrations/2026_06_24_000001_update_scheduled_tasks_table_for_media.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            // Rendre article_id nullable (pour les médias)
            if (Schema::hasColumn('scheduled_tasks', 'article_id')) {
                $table->foreignId('article_id')->nullable()->change();
            }
            
            //  les colonnes seulement si elles n'existent pas
            if (!Schema::hasColumn('scheduled_tasks', 'media_id')) {
                $table->unsignedBigInteger('media_id')->nullable()->after('article_id');
            }
            
            if (!Schema::hasColumn('scheduled_tasks', 'platform')) {
                $table->string('platform')->nullable()->after('media_id');
            }
            
            if (!Schema::hasColumn('scheduled_tasks', 'caption')) {
                $table->text('caption')->nullable()->after('platform');
            }
            
            if (!Schema::hasColumn('scheduled_tasks', 'metadata')) {
                $table->json('metadata')->nullable()->after('caption');
            }
            
            // ⚠️ error_message existe déjà, ne pas l'
            if (!Schema::hasColumn('scheduled_tasks', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('error_message');
            }
            
            if (!Schema::hasColumn('scheduled_tasks', 'type')) {
                $table->string('type')->default('article')->after('id');
            }
            
            //  les index seulement s'ils n'existent pas
            try {
                $table->index(['type', 'status']);
            } catch (\Exception $e) {
                // Index existe déjà
            }
            
            try {
                $table->index(['media_id', 'platform']);
            } catch (\Exception $e) {
                // Index existe déjà
            }
            
            try {
                $table->index('scheduled_at');
            } catch (\Exception $e) {
                // Index existe déjà
            }
            
            //  la contrainte de clé étrangère seulement si elle n'existe pas
            try {
                $table->foreign('media_id')->references('id')->on('user_media')->onDelete('cascade');
            } catch (\Exception $e) {
                // Contrainte existe déjà
            }
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            // Supprimer les clés étrangères et index
            try {
                $table->dropForeign(['media_id']);
            } catch (\Exception $e) {
                // La clé étrangère n'existe pas
            }
            
            try {
                $table->dropIndex(['type', 'status']);
            } catch (\Exception $e) {
                // L'index n'existe pas
            }
            
            try {
                $table->dropIndex(['media_id', 'platform']);
            } catch (\Exception $e) {
                // L'index n'existe pas
            }
            
            try {
                $table->dropIndex(['scheduled_at']);
            } catch (\Exception $e) {
                // L'index n'existe pas
            }
            
            // Supprimer les colonnes ajoutées (sauf error_message qui existait déjà)
            $columnsToDrop = [];
            
            if (Schema::hasColumn('scheduled_tasks', 'type')) {
                $columnsToDrop[] = 'type';
            }
            if (Schema::hasColumn('scheduled_tasks', 'media_id')) {
                $columnsToDrop[] = 'media_id';
            }
            if (Schema::hasColumn('scheduled_tasks', 'platform')) {
                $columnsToDrop[] = 'platform';
            }
            if (Schema::hasColumn('scheduled_tasks', 'caption')) {
                $columnsToDrop[] = 'caption';
            }
            if (Schema::hasColumn('scheduled_tasks', 'metadata')) {
                $columnsToDrop[] = 'metadata';
            }
            if (Schema::hasColumn('scheduled_tasks', 'processed_at')) {
                $columnsToDrop[] = 'processed_at';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
            
            // Remettre article_id comme non nullable (si possible)
            if (Schema::hasColumn('scheduled_tasks', 'article_id')) {
                try {
                    $table->foreignId('article_id')->nullable(false)->change();
                } catch (\Exception $e) {
                    // Impossible de modifier la colonne
                }
            }
        });
    }
};