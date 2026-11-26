<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCAT6 (Outils, médecins) : patients du médecin et leurs examens.
 * Données anonymisées par le médecin : un nom libre (initiales, code…) et l'année de naissance seulement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scat_patients', function (Blueprint $table) {
            $table->id();
            // Médecin propriétaire : un patient n'est visible que par lui.
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 100);
            $table->unsignedSmallInteger('birth_year');
            // football / judo / hockey_glace / general (Scat6Content::SPORTS).
            $table->string('sport', 20);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scat_assessments', function (Blueprint $table) {
            $table->id();
            // Généré par l'application dès le début de l'examen : la sauvegarde automatique se fait par uuid.
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('scat_patient_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            // post_choc / baseline / retest.
            $table->string('exam_kind', 20);
            // draft → completed (signé, plus modifiable).
            $table->string('status', 12)->default('draft');
            // Dernière étape atteinte, pour reprendre un brouillon.
            $table->string('step', 30)->nullable();
            $table->string('content_version', 40);
            $table->char('word_list', 1)->default('A');
            $table->timestamp('injury_at')->nullable();
            // answers, tests, skipped, percent_normal, background_notes (format de l'application).
            $table->json('data')->nullable();
            // Scores recalculés par le serveur à la signature (Scat6Scoring::summarize).
            $table->json('summary')->nullable();
            $table->string('neuro', 15)->nullable();
            $table->string('diagnosis', 10)->nullable();
            $table->text('notes')->nullable();
            // Nom du médecin au moment de la signature : un changement de nom ne modifie pas un rapport signé.
            $table->string('signed_by', 191)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scat_patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scat_assessments');
        Schema::dropIfExists('scat_patients');
    }
};
