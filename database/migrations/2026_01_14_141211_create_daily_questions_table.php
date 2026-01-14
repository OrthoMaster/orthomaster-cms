<?php

use App\Models\Topic;
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
        Schema::create('daily_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Topic::class);
            $table->string('question');
            $table->string('explanation')->nullable();
            $table->string('image')->nullable();
            $table->integer('count')->default(0);
            $table->datetime('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_questions');
    }
};
