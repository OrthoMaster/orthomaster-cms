<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une ligne par tour de conversation avec l'assistant, y compris les refus
     * (quota atteint, assistant indisponible) : c'est la source du tableau de bord.
     */
    public function up(): void
    {
        Schema::create('ai_search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->char('session_id', 36)->nullable()->index();
            $table->unsignedSmallInteger('round')->nullable();

            // message (texte saisi) | option (pastille choisie) | show (« Voir les N vidéos »)
            $table->string('input_type', 10);
            // ask, ask_edition, edition_not_found, show, show_now, no_candidates
            $table->string('action', 30)->nullable();
            $table->string('status', 10);
            // rate_limited, ai_unavailable, session_expired, invalid_request
            $table->string('error_code', 30)->nullable();

            $table->unsignedSmallInteger('result_count')->nullable();
            $table->unsignedSmallInteger('candidate_count')->nullable();
            // search (recherche par le sens) | browse (toutes les vidéos d'un congrès)
            $table->string('retrieval_mode', 10)->nullable();

            $table->boolean('used_llm')->default(false);
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('thought_tokens')->default(0);
            // Estimation (caractères / 4) : l'API d'embeddings ne renvoie pas de décompte.
            $table->unsignedInteger('embedding_tokens')->default(0);

            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('llm_ms')->nullable();

            // Rempli seulement si ai_search.logging.log_queries est activé.
            $table->text('query_text')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_search_logs');
    }
};