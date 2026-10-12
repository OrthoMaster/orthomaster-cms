<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experts', function (Blueprint $table) {
            // Version publiée de la page partenaire (copiée depuis le brouillon par « Publier »)
            $table->string('honorific', 40)->nullable();      // Docteur, Professeur…
            $table->string('credentials', 200)->nullable();   // titres et diplômes
            $table->text('lede')->nullable();                 // courte présentation
            $table->string('palette', 20)->nullable();
            $table->string('model', 20)->nullable();
            $table->json('brochure')->nullable();             // lieux, liens, titres, formation, parcours
            $table->timestamp('brochure_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('experts', function (Blueprint $table) {
            $table->dropColumn(['honorific', 'credentials', 'lede', 'palette', 'model', 'brochure', 'brochure_published_at']);
        });
    }
};