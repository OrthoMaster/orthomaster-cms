<?php

use App\Models\Video;
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
        Schema::create('infographics', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Video::class)->nullable();
            $table->string('code', 20)->unique();
            $table->string('type', 10)->nullable()->default('info');
            $table->string('title');
            $table->string('thumbnail')->nullable();
            $table->string('image')->nullable();
            $table->string('notification_title', 150)->nullable();
            $table->string('notification_body')->nullable();
            $table->date('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infographics');
    }
};
