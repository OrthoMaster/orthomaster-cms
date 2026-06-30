<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quizz_questions')->cascadeOnDelete();
            $table->text('content');
            $table->integer('score')->default(0);   // points attribués pour cette réponse (ni bonne ni mauvaise)
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizz_answers');
    }
};
