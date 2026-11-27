<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCAT6 › Retour au sport (RTS) : un suivi par commotion, 6 étapes (Amsterdam 2022).
 * Le médecin fait avancer les étapes (consultation ou téléphone) ; un seul suivi actif par patient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scat_rts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scat_patient_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            // Examen signé « commotion : Oui » qui a ouvert le suivi (null : ouvert à la main).
            $table->uuid('assessment_uuid')->nullable();
            $table->date('injury_at');
            // Étape en cours (1 → 6) ; 6 atteinte = retour à la compétition autorisé.
            $table->unsignedTinyInteger('current_step')->default(1);
            // active → completed (étape 6 atteinte) ; closed = suivi arrêté sans reprise enregistrée.
            $table->string('status', 12)->default('active');
            // { "1": { started_at, done_at, forced, note }, … } et [{ at, from, to, note }, …]
            $table->json('steps');
            $table->json('setbacks')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scat_patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scat_rts');
    }
};
