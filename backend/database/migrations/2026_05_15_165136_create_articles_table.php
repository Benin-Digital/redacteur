<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('titre');
            $table->string('meta_desc', 160)->nullable();
            $table->string('slug')->unique();
            $table->longText('contenu_md');
            $table->longText('contenu_html')->nullable();
            $table->string('categorie')->nullable();
            $table->json('tags')->nullable();
            $table->string('mot_cle_principal')->nullable();
            $table->enum('statut', ['brouillon', 'programme', 'publie'])->default('brouillon');
            $table->timestamp('programme_le')->nullable();
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};