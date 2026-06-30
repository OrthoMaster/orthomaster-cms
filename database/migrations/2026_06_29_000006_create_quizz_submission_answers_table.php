<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizz_submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('quizz_submissions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('quizz_questions')->cascadeOnDelete();
            $table->foreignId('answer_id')->nullable()->constrained('quizz_answers')->nullOnDelete(); // null si temps écoulé
            $table->integer('score_earned')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizz_submission_answers');
    }
};
