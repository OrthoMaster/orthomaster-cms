<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAds extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'priority'          => 'integer',
        'total_impressions' => 'integer',
        'total_views'       => 'integer',
    ];

    public function adsCompany()
    {
        return $this->belongsTo(AdsCompany::class);
    }

    public function professions()
    {
        return $this->belongsToMany(
            Profession::class,
            'profession_video_ad',
            'video_ads_id',
            'profession_id'
        )->withTimestamps();
    }

    public function impressions()
    {
        return $this->hasMany(VideoAdImpression::class);
    }
 
    public function views()
    {
        return $this->hasMany(VideoAdView::class);
    }
 
    public function dailyStats()
    {
        return $this->hasMany(VideoAdDailyStat::class);
    }

    public function incrementImpressions(int $amount = 1): void
    {
        $this->newQuery()
            ->whereKey($this->getKey())
            ->increment('total_impressions', $amount);
    }

    public function incrementViews(int $amount = 1): void
    {
        $this->newQuery()
            ->whereKey($this->getKey())
            ->increment('total_views', $amount);
    }
}
