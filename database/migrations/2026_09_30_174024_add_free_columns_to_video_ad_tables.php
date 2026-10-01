<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une consultation est offerte lorsqu'elle provient d'un participant
        // de l'édition parrainée, ou de l'auteur du contenu lui-même
        Schema::table('video_ad_impressions', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('user_id');
            $table->index('is_free', 'idx_impression_free');
        });

        Schema::table('video_ad_views', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('user_id');
            $table->index('is_free', 'idx_view_free');
        });

        // Part non facturable, isolée dans chaque table d'agrégats
        Schema::table('video_ad_daily_stats', function (Blueprint $table) {
            $table->unsignedInteger('free_impressions_count')->default(0)->after('impressions_count');
            $table->unsignedInteger('free_views_count')->default(0)->after('views_count');
            $table->unsignedInteger('free_completed_views_count')->default(0)->after('completed_views_count');
            $table->unsignedInteger('free_contacted_count')->default(0)->after('contacted_count');
        });

        Schema::table('video_ad_profession_stats', function (Blueprint $table) {
            $table->unsignedInteger('free_impressions_count')->default(0)->after('impressions_count');
            $table->unsignedInteger('free_views_count')->default(0)->after('views_count');
            $table->unsignedInteger('free_completed_views_count')->default(0)->after('completed_views_count');
            $table->unsignedInteger('free_contacted_count')->default(0)->after('contacted_count');
        });

        Schema::table('video_ad_topic_stats', function (Blueprint $table) {
            $table->unsignedInteger('free_impressions_count')->default(0)->after('impressions_count');
            $table->unsignedInteger('free_views_count')->default(0)->after('views_count');
            $table->unsignedInteger('free_completed_views_count')->default(0)->after('completed_views_count');
            $table->unsignedInteger('free_contacted_count')->default(0)->after('contacted_count');
        });
    }

    public function down(): void
    {
        Schema::table('video_ad_topic_stats', function (Blueprint $table) {
            $table->dropColumn([
                'free_impressions_count',
                'free_views_count',
                'free_completed_views_count',
                'free_contacted_count',
            ]);
        });

        Schema::table('video_ad_profession_stats', function (Blueprint $table) {
            $table->dropColumn([
                'free_impressions_count',
                'free_views_count',
                'free_completed_views_count',
                'free_contacted_count',
            ]);
        });

        Schema::table('video_ad_daily_stats', function (Blueprint $table) {
            $table->dropColumn([
                'free_impressions_count',
                'free_views_count',
                'free_completed_views_count',
                'free_contacted_count',
            ]);
        });

        Schema::table('video_ad_views', function (Blueprint $table) {
            $table->dropIndex('idx_view_free');
            $table->dropColumn('is_free');
        });

        Schema::table('video_ad_impressions', function (Blueprint $table) {
            $table->dropIndex('idx_impression_free');
            $table->dropColumn('is_free');
        });
    }
};