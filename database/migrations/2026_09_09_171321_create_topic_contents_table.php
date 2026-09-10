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
        Schema::create('topic_content', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Topic::class);
            $table->string('content_type', 20);
            $table->unsignedBigInteger('content_id');
            $table->timestamps();

            $table->unique(['topic_id', 'content_type', 'content_id'], 'uniq_topic_content');
            $table->index(['content_type', 'content_id'], 'idx_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topic_content');
    }
};
