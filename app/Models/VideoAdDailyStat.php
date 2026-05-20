<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VideoAdDailyStat extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'stat_date'             => 'date',
        'impressions_count'     => 'integer',
        'views_count'           => 'integer',
        'completed_views_count' => 'integer',
        'contacted_count'       => 'integer',
    ];

    public function videoAd()
    {
        return $this->belongsTo(VideoAds::class);
    }

    public function adsCompany()
    {
        return $this->belongsTo(AdsCompany::class);
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('stat_date', [$from, $to]);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('ads_company_id', $companyId);
    }

    public function scopeForVideoAd(Builder $query, int $videoAdId): Builder
    {
        return $query->where('video_ads_id', $videoAdId);
    }
}
