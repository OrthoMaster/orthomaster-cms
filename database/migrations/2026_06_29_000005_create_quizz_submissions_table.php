<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizz_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('quizz_topics')->cascadeOnDelete();
            $table->string('user_name');
            $table->string('email')->nullable();              // saisi après l'affichage du résultat
            $table->boolean('allow_contact')->default(false);
            $table->integer('raw_score')->default(0);         // total des points obtenus
            $table->integer('max_possible_score')->default(0);// total maximum possible
            $table->decimal('final_score', 5, 2)->default(0); // sur 100
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizz_submissions');
    }
};
