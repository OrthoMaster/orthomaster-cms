<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entreprises finançant une édition : leurs publicités sont mises
        // en avant sur les contenus qui en sont issus
        Schema::create('past_event_sponsors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('past_event_id');
            $table->unsignedBigInteger('ads_company_id');
            $table->timestamps();

            $table->unique(['past_event_id', 'ads_company_id'], 'uniq_event_sponsor');
            $table->index('ads_company_id', 'idx_sponsor_company');

            $table->foreign('past_event_id')->references('id')->on('past_events')->onDelete('cascade');
            $table->foreign('ads_company_id')->references('id')->on('ads_companies')->onDelete('cascade');
        });

        // Personnes présentes à l'édition : les publicités qu'elles
        // consultent ne sont pas facturées aux sponsors
        Schema::create('past_event_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('past_event_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['past_event_id', 'user_id'], 'uniq_event_participant');
            $table->index('user_id', 'idx_participant_user');

            $table->foreign('past_event_id')->references('id')->on('past_events')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Paramètres de l'avantage consenti aux sponsors
        Schema::create('past_event_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('past_event_id')->unique();

            // Multiplie le score d'affichage des publicités des sponsors
            $table->decimal('ads_multiplier', 4, 2)->default(2);

            // Durée de l'avantage, comptée depuis la publication du contenu
            $table->unsignedSmallInteger('ads_months')->default(1);

            $table->timestamps();

            $table->foreign('past_event_id')->references('id')->on('past_events')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_event_settings');
        Schema::dropIfExists('past_event_users');
        Schema::dropIfExists('past_event_sponsors');
    }
};