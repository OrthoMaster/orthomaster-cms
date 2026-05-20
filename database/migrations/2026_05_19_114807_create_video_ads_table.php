<?php

use App\Models\AdsCompany;
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
        Schema::create('video_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(AdsCompany::class);
            $table->string('title');
            $table->string('video_url', 1024);
            $table->string('thumbnail_url', 1024)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedBigInteger('total_impressions')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);

            $table->timestamps();
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_ads');
    }
};
