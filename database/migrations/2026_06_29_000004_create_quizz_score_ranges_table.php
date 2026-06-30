<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizz_score_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('quizz_topics')->cascadeOnDelete();
            $table->integer('min_score');           // sur 100, ex : 0
            $table->integer('max_score');           // sur 100, ex : 39
            $table->string('label')->nullable();    // ex : "Débutant"
            $table->string('title')->nullable();    // titre du verdict
            $table->text('comment');                // texte de commentaire
            $table->string('color')->nullable();    // couleur du badge
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizz_score_ranges');
    }
};
