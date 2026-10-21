<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signalements d'absence (no-show) sur un rendez-vous accepté.
 * Chaque partie signale au plus une fois par rendez-vous ; le traitement
 * (remboursement selon la politique, classement) se fera depuis le CMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_noshow_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            // 'user' : le demandeur signale l'absence de l'expert ; 'expert' : l'inverse.
            $table->string('reporter', 10);
            $table->unsignedBigInteger('reporter_user_id');
            // Demi-journées concernées (booking_blocks.id).
            $table->json('block_ids');
            $table->string('note', 500)->nullable();
            // open → resolved | dismissed (CMS).
            $table->string('status', 20)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'reporter']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_noshow_reports');
    }
};