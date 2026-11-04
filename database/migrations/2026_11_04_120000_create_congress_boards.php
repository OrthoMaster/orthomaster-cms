<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions du public pendant les tables rondes : une table ronde = un tableau.
 * Le public ajoute en fin de file, l'admin ordonne chaque thème et supprime les questions traitées ;
 * l'écran de projection n'affiche que le début de chaque file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('congress_boards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('past_event_id')->nullable()->index();
            $table->string('congress_name', 150)->nullable();
            $table->string('title', 150);
            // Code court : imprimé sous le QR, doit pouvoir être saisi à la main.
            $table->string('code', 8)->unique();
            $table->string('screen_token', 64)->unique();
            $table->string('status', 10)->default('draft'); // draft | live | closed
            // Incrémenté à chaque changement visible : l'écran interroge en boucle et n'a rien à relire si rien n'a bougé.
            $table->unsignedInteger('revision')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('congress_board_themes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id');
            $table->string('name', 60);
            $table->char('color', 7);
            $table->unsignedTinyInteger('order_dsp')->default(1);
            $table->timestamps();

            $table->index(['board_id', 'order_dsp']);
        });

        Schema::create('congress_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id');
            $table->string('name', 60);
            // Empreinte du jeton d'appareil ; le jeton lui-même ne reste que sur le téléphone.
            $table->char('token_hash', 64)->unique();
            $table->timestamps();

            $table->index('board_id');
        });

        Schema::create('congress_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id');
            $table->unsignedBigInteger('theme_id');
            $table->unsignedBigInteger('participant_id');
            $table->string('text', 120);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['theme_id', 'position']);
            $table->index('board_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('congress_questions');
        Schema::dropIfExists('congress_participants');
        Schema::dropIfExists('congress_board_themes');
        Schema::dropIfExists('congress_boards');
    }
};
