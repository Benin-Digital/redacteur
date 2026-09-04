<?php
// database/migrations/2026_06_23_080345_add_capabilities_to_system_api_keys_table.php

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
        Schema::table('system_api_keys', function (Blueprint $table) {
            //  la colonne capabilities si elle n'existe pas
            if (!Schema::hasColumn('system_api_keys', 'capabilities')) {
                $table->json('capabilities')->nullable()->after('model_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('system_api_keys', 'capabilities')) {
                $table->dropColumn('capabilities');
            }
        });
    }
};