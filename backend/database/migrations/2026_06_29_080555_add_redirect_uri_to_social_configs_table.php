<?php
// database/migrations/xxxx_add_redirect_uri_to_social_configs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('social_configs', 'redirect_uri')) {
                $table->string('redirect_uri')->nullable()->after('client_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_configs', function (Blueprint $table) {
            if (Schema::hasColumn('social_configs', 'redirect_uri')) {
                $table->dropColumn('redirect_uri');
            }
        });
    }
};