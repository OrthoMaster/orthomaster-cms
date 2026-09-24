<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAdBudget extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'points_total'   => 'integer',
    ];

    /** Enveloppe par défaut, à la souscription */
    public const DEFAULT_POINTS = 100;

    public function videoAd()
    {
        return $this->belongsTo(VideoAds::class, 'video_ads_id');
    }
}