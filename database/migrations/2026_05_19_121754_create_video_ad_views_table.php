<?php

use App\Models\User;
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
        Schema::create('video_ad_views', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(VideoAds::class);
            $table->foreignIdFor(User::class);
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->index(['video_ads_id', 'created_at']);
            $table->index('created_at');
            $table->index('user_id');
            $table->index('is_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_ad_views');
    }
};
