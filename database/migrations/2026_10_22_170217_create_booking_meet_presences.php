<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fermeture automatique des salles Meet et relevé des présences.
 *
 * booking_blocks :
 *  - meet_closed_at       : salle fermée (accès restreint) ; le lien ne fonctionne plus.
 *  - meet_close_reason    : empty | alone | max_duration | booking_closed | admin.
 *  - meet_reopened_until  : rouverte par l'admin ; le job ne la referme pas avant cette heure.
 *  - meet_synced_at       : dernier relevé complet des présences.
 *
 * booking_meet_presences : une ligne par passage (session) d'un participant dans la salle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_blocks', function (Blueprint $table) {
            $table->timestamp('meet_closed_at')->nullable()->after('meet_created_at');
            $table->string('meet_close_reason', 20)->nullable()->after('meet_closed_at');
            $table->timestamp('meet_reopened_until')->nullable()->after('meet_close_reason');
            $table->timestamp('meet_synced_at')->nullable()->after('meet_reopened_until');
        });

        Schema::create('booking_meet_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('booking_blocks')->cascadeOnDelete();
            // Identifiants Google : conferenceRecords/…, …/participants/…, …/participantSessions/…
            $table->string('conference', 190);
            $table->string('participant', 255);
            $table->string('session', 255)->unique();
            $table->string('display_name')->nullable();
            $table->string('kind', 12); // google | anonymous | phone
            $table->string('google_user', 120)->nullable(); // users/xxxx (comptes Google)
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable(); // null : encore dans la salle au moment du relevé
            $table->timestamps();

            $table->index(['block_id', 'participant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_meet_presences');

        Schema::table('booking_blocks', function (Blueprint $table) {
            $table->dropColumn(['meet_closed_at', 'meet_close_reason', 'meet_reopened_until', 'meet_synced_at']);
        });
    }
};