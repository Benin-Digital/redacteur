<?php
// database/migrations/xxxx_xx_xx_add_two_factor_columns_to_users_table.php

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
        Schema::table('users', function (Blueprint $table) {
            // Ajouter les colonnes pour la 2FA
            $table->string('two_factor_code', 6)->nullable()->after('remember_token');
            $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            $table->timestamp('two_factor_verified_at')->nullable()->after('two_factor_expires_at');
            
            // Ajouter des index pour accélérer les recherches
            $table->index('two_factor_code');
            $table->index('two_factor_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_code',
                'two_factor_expires_at',
                'two_factor_verified_at'
            ]);
        });
    }
};