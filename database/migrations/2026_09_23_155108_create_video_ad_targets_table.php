<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'enveloppe appartient à la répartition, pas à la publicité :
        // une entreprise peut souscrire davantage de points d'un mois à l'autre
        Schema::create('video_ad_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_ads_id');
            $table->date('effective_from');
            $table->unsignedInteger('points_total')->default(100);

            $table->timestamps();

            $table->unique(['video_ads_id', 'effective_from'], 'uniq_video_ad_budget');

            $table->foreign('video_ads_id')->references('id')->on('video_ads')->onDelete('cascade');
        });

        // Répartition en vigueur : modifiable à tout moment par l'administration
        Schema::create('video_ad_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_ads_id');
            $table->date('effective_from');

            // 'profession' | 'topic'
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->unsignedInteger('points')->default(0);

            $table->timestamps();

            $table->index(['target_type', 'target_id'], 'idx_target');
            $table->unique(
                ['video_ads_id', 'effective_from', 'target_type', 'target_id'],
                'uniq_video_ad_target'
            );
            $table->index(['video_ads_id', 'effective_from'], 'idx_effective');

            $table->foreign('video_ads_id')->references('id')->on('video_ads')->onDelete('cascade');
        });

        // Photographie mensuelle : le rapport d'un mois clos ne bouge plus
        Schema::create('video_ad_target_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7); // AAAA-MM
            $table->unsignedBigInteger('video_ads_id');
            $table->unsignedBigInteger('ads_company_id');
            $table->unsignedInteger('points_total');
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('target_name');
            $table->unsignedInteger('points');

            $table->timestamps();

            $table->unique(['period', 'video_ads_id', 'target_type', 'target_id'], 'uniq_snapshot');
            $table->index(['period', 'ads_company_id'], 'idx_period_company');
        });

        // Impressions et vues par profession visée
        Schema::create('video_ad_profession_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('video_ads_id');
            $table->unsignedBigInteger('ads_company_id');
            $table->unsignedBigInteger('profession_id');

            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('completed_views_count')->default(0);
            $table->unsignedInteger('contacted_count')->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'video_ads_id', 'profession_id'], 'uniq_profession_stat');
            $table->index(['ads_company_id', 'stat_date'], 'idx_company_date');
        });

        // Impressions et vues par sujet visé
        Schema::create('video_ad_topic_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedBigInteger('video_ads_id');
            $table->unsignedBigInteger('ads_company_id');
            $table->unsignedBigInteger('topic_id');

            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('completed_views_count')->default(0);
            $table->unsignedInteger('contacted_count')->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'video_ads_id', 'topic_id'], 'uniq_topic_stat');
            $table->index(['ads_company_id', 'stat_date'], 'idx_company_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_ad_topic_stats');
        Schema::dropIfExists('video_ad_profession_stats');
        Schema::dropIfExists('video_ad_target_snapshots');
        Schema::dropIfExists('video_ad_targets');
        Schema::dropIfExists('video_ad_budgets');
    }
};