<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAdImpression extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function videoAd()
    {
        return $this->belongsTo(VideoAds::class);
    }
}
