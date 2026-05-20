<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsCompany extends Model
{
    protected $guarded = ['id'];

    public function videoAds()
    {
        return $this->hasMany(VideoAds::class);
    }

    public function dailyStats()
    {
        return $this->hasMany(VideoAdDailyStat::class);
    }
}
