<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Version aplatie de chaque média publié : sert au filtrage, aux facettes et à la
        // recherche plein texte sans rejouer les jointures à chaque requête.
        // Pas de clé étrangère : content.id_content n'a pas un type garanti et l'index
        // se reconstruit de toute façon chaque nuit.
        Schema::create('ai_search_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('content_id')->primary();
            $table->string('version', 10)->nullable();
            $table->string('title', 500);
            $table->text('experts')->nullable();
            $table->json('expert_ids')->nullable();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->string('event_name')->nullable();
            $table->unsignedBigInteger('past_event_id')->nullable()->index();
            $table->string('edition_year', 50)->nullable();
            $table->longText('search_text');
            $table->char('content_hash', 64);
            $table->timestamp('indexed_at')->nullable();

            // Deux index séparés pour pouvoir pondérer titre / intervenants plus fort que le reste.
            $table->fullText(['title', 'experts'], 'ai_docs_ft_title');
            $table->fullText('search_text', 'ai_docs_ft_all');
        });

        Schema::create('ai_search_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_id')->index();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('timestamp_sec')->nullable();
            $table->text('text');
            // Hash du texte réellement envoyé à l'API : permet de réutiliser l'embedding
            // quand un média est modifié sans que ce morceau-là change.
            $table->char('text_hash', 64);
            $table->string('model', 64);
            $table->unsignedSmallInteger('dimensions');
            // float32 little-endian, 768 × 4 = 3 Ko par morceau.
            $table->binary('embedding');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_search_chunks');
        Schema::dropIfExists('ai_search_documents');
    }
};
