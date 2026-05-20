<?php

use App\Models\AdsCompany;
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
        Schema::create('video_ad_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(VideoAds::class);
            $table->foreignIdFor(AdsCompany::class);
            $table->date('stat_date');
 
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('completed_views_count')->default(0);
            $table->timestamps();

            $table->unique(['video_ads_id', 'stat_date']);
            $table->index('stat_date');
            $table->index(['ads_company_id', 'stat_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_ad_daily_stats');
    }
};
