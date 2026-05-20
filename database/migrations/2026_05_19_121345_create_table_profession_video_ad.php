<?php

use App\Models\Profession;
use App\Models\VideoAds;
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
        Schema::create('table_profession_video_ad', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(VideoAds::class);
            $table->foreignIdFor(Profession::class);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_profession_video_ad');
    }
};
