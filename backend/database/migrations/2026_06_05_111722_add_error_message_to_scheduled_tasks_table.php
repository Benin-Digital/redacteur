<?php
// migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->string('error_message')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });
    }
};