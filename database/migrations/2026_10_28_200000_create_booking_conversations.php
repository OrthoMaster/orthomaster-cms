<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie entre un expert et un confrère qui l'a réservé.
 *
 * booking_conversations : un seul fil par couple (expert, demandeur), tous rendez-vous confondus.
 *  - opened_at            : premier rendez-vous accepté ; avant, le fil ne montre que les
 *                           événements et personne ne peut écrire. Jamais refermé ensuite.
 *  - last_message_id/_at  : tri de la liste et calcul des non-lus sans parcourir les messages.
 *  - user/expert_last_read_id : dernier message vu par chaque partie (non-lus, « Vu »).
 *  - blocked_attempts     : messages refusés par le filtre de coordonnées, pour repérer
 *                           les tentatives de contourner la plateforme.
 *
 * booking_messages : messages écrits (kind = text) et événements des rendez-vous (kind = event).
 *  - sender          : user | expert | admin | system — la partie, pas le compte.
 *  - booking_event_id: ligne du journal d'origine ; unique, la reprise peut repasser sans doublon.
 *  - client_id       : identifiant généré par l'application, renvoyer le même message
 *                      après une coupure réseau ne le duplique pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('opened_at')->nullable();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedBigInteger('user_last_read_id')->default(0);
            $table->unsignedBigInteger('expert_last_read_id')->default(0);
            $table->unsignedInteger('blocked_attempts')->default(0);
            $table->timestamps();

            $table->unique(['expert_id', 'user_id']);
            $table->index(['user_id', 'last_message_at']);
            $table->index('last_message_at');
        });

        Schema::create('booking_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('kind', 10);
            $table->string('sender', 10);
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->text('body')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('booking_event_id')->nullable()->unique();
            $table->string('event_type', 40)->nullable();
            $table->json('data')->nullable();
            $table->string('client_id', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'id']);
            $table->unique(['conversation_id', 'client_id']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_messages');
        Schema::dropIfExists('booking_conversations');
    }
};
