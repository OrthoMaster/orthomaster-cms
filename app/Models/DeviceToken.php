<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken()
    {
        return $this->belongsTo(PersonalAccessToken::class, 'access_token_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($deviceToken) {
            if ($deviceToken->accessToken) {
                $deviceToken->accessToken->delete();
            }
        });
    }
}
