<?php
// database/migrations/2026_06_23_150627_add_missing_social_columns_to_user_media_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_media', function (Blueprint $table) {
            //  uniquement les colonnes manquantes
            if (!Schema::hasColumn('user_media', 'published_to_facebook')) {
                $table->boolean('published_to_facebook')->default(false)->after('published_to_instagram');
            }
            
            if (!Schema::hasColumn('user_media', 'published_to_twitter')) {
                $table->boolean('published_to_twitter')->default(false)->after('published_to_facebook');
            }
            
            if (!Schema::hasColumn('user_media', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('published_to_twitter');
            }
            
            if (!Schema::hasColumn('user_media', 'publication_results')) {
                $table->json('publication_results')->nullable()->after('published_at');
            }
            
            //  les index manquants
            try {
                $table->index(['user_id', 'published_to_tiktok']);
            } catch (\Exception $e) {
                // Index existe déjà
            }
            
            try {
                $table->index(['user_id', 'published_to_instagram']);
            } catch (\Exception $e) {
                // Index existe déjà
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_media', function (Blueprint $table) {
            $columns = [
                'published_to_facebook',
                'published_to_twitter',
                'published_at',
                'publication_results'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('user_media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};