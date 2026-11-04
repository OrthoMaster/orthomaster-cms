<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hors calendrier par créneaux : le demandeur propose 1 à 3 plages (sur un ou plusieurs jours),
 * l'expert choisit l'horaire exact dans l'une d'elles en acceptant.
 * Consultation : heures (pas de 30 minutes). Thème : demi-journées.
 * Tant que l'expert n'a pas choisi, la demande n'a aucune demi-journée (booking_blocks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedTinyInteger('position')->default(1);
            // Dates et horaires locaux de l'expert, tels que proposés.
            $table->date('start_date');
            $table->char('start_time', 5)->nullable();      // consultation : HH:MM
            $table->string('start_period', 10)->nullable(); // thème : morning | afternoon
            $table->date('end_date');
            $table->char('end_time', 5)->nullable();
            $table->string('end_period', 10)->nullable();
            // Étendue complète en UTC (tri, délai de réponse).
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->timestamps();

            $table->index(['booking_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_windows');
    }
};
