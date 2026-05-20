<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAdView extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_completed'    => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function videoAd()
    {
        return $this->belongsTo(VideoAds::class);
    }
}
