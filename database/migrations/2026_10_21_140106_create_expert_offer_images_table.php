<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Illustrations d'un thème (schémas, photos opératoires…), affichées sur sa fiche.
 * Fichiers sur le disque S3, dossier expert_topic_image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_offer_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->string('path', 255);
            $table->string('caption', 150)->nullable();
            // Dimensions après redimensionnement : l'application réserve la place sans attendre le chargement.
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('order_dsp')->default(0);
            $table->timestamps();

            $table->index(['offer_id', 'order_dsp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_offer_images');
    }
};