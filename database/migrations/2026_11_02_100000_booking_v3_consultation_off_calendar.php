<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous v3 :
 *  - Consultation (sur mesure) : demande à un expert sur un sujet libre, en ligne,
 *    durée par tranches de 30 minutes, prix de l'expert pour 30 minutes. Pas d'offre.
 *  - Hors calendrier : une demande peut porter sur une date que l'expert n'a pas ouverte.
 *  - Recherche intelligente des experts et des thèmes (vecteurs d'embeddings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Une consultation n'a pas d'offre.
            $table->unsignedBigInteger('offer_id')->nullable()->change();
            // offer : thème de l'expert ; consultation : sujet libre.
            $table->string('kind', 20)->default('offer')->after('offer_id');
            // Au moins une demi-journée (ou l'horaire de la consultation) hors du calendrier de l'expert.
            $table->boolean('off_calendar')->default(false)->after('kind');
            // Consultation : durée demandée (multiple de 30) et sujet décrit par le demandeur.
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('blocks_count');
            $table->text('subject')->nullable()->after('message');

            $table->index(['expert_id', 'kind']);
        });

        Schema::table('booking_blocks', function (Blueprint $table) {
            // Demi-journée hors calendrier : en présentiel, le lieu est choisi par l'expert à l'acceptation.
            $table->boolean('off_calendar')->default(false)->after('location');
        });

        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->boolean('consultation_enabled')->default(false);
            // Prix TTC de 30 minutes.
            $table->decimal('consultation_price', 8, 2)->nullable();
            // Durée maximale qu'un demandeur peut choisir (null : valeur système).
            $table->unsignedSmallInteger('consultation_max_minutes')->nullable();
        });

        /* --- Recherche intelligente : un vecteur par expert et par thème --- */
        Schema::create('booking_search_vectors', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);            // expert | theme
            $table->unsignedBigInteger('ref_id');  // experts.id | expert_offers.id
            // sha1(modèle + texte indexé) : un changement de texte ou de modèle réindexe.
            $table->char('hash', 40);
            $table->string('model', 80);
            $table->unsignedSmallInteger('dims');
            $table->binary('vector');
            $table->timestamps();

            $table->unique(['type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_search_vectors');

        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->dropColumn(['consultation_enabled', 'consultation_price', 'consultation_max_minutes']);
        });

        Schema::table('booking_blocks', function (Blueprint $table) {
            $table->dropColumn('off_calendar');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['expert_id', 'kind']);
            $table->dropColumn(['kind', 'off_calendar', 'duration_minutes', 'subject']);
        });
    }
};
