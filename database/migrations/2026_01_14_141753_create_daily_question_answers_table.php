<?php

use App\Models\DailyQuestion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(DailyQuestion::class);
            $table->string('text');
            $table->boolean('correct_flg')->default(false);
            $table->integer('count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_question_answers');
    }
};
