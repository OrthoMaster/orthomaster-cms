<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Qualiopi ind. 30 : un questionnaire « à froid » par apprenant et par devis signé.
        Schema::create('satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('cart_id');
            $table->string('contract_no')->nullable();
            $table->string('organization')->nullable();
            // Date du devis signé : point de départ des 3 mois.
            $table->timestamp('purchased_at')->index();
            // Figés à la création : renommer un paquet ne doit pas changer ce que l'apprenant a évalué.
            $table->json('package_ids');
            $table->text('packages_label');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('answered_at')->nullable()->index();

            // Notes de 1 à 5 : elles forment le score.
            $table->unsignedTinyInteger('rating_global')->nullable();
            $table->unsignedTinyInteger('rating_objectives')->nullable();
            $table->unsignedTinyInteger('rating_content')->nullable();
            $table->unsignedTinyInteger('rating_trainers')->nullable();
            $table->unsignedTinyInteger('rating_platform')->nullable();
            $table->unsignedTinyInteger('rating_skills')->nullable();
            $table->decimal('score', 3, 2)->nullable();

            // Hors score.
            $table->string('practice', 20)->nullable();
            $table->unsignedTinyInteger('recommend')->nullable();
            $table->boolean('difficulties_flg')->nullable();
            $table->text('difficulties_text')->nullable();
            $table->text('liked_text')->nullable();
            $table->text('suggestions_text')->nullable();
            $table->boolean('recontact_flg')->default(false);

            $table->timestamps();

            $table->unique(['cart_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_surveys');
    }
};
