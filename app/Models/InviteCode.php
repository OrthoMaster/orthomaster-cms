<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'permanent_flg' => 'boolean',
        'active_flg' => 'boolean',
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'used_times' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('active_flg', true);
    }

    public static function checkCode($code)
    {
        $code = InviteCode::where('code', $code)->active()->first();

        if (!$code) {
            return 'code-invalid';
        }

        if ($code->permanent_flg) {
            return 'code-valid';
        }

        $now = now();
        $validFrom = $code->valid_from;
        $expiresAt = $code->expires_at;

        // Check expire
        $isAfterStart = is_null($validFrom) || $validFrom <= $now;
        $isBeforeEnd = is_null($expiresAt) || $expiresAt >= $now;
        if ($isAfterStart && $isBeforeEnd) {
            return 'code-valid';
        }

        return 'code-expired';
    }

    public static function increaseUsedTimes($code) {
        $code = InviteCode::where('code', $code)->active()->first();
        $code->used_times += 1;
        $code->save();
        
        return;
    }
}
