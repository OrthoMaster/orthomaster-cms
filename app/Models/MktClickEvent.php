<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MktClickEvent extends Model
{
    protected $table = 'mkt_click_events';

    protected $fillable = [
        'short_link_id', 'source_type', 'ip_address', 'ip_hash', 'user_agent',
        'device_type', 'browser', 'platform', 'referer', 'country', 'is_bot',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
    ];

    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(MktShortLink::class, 'short_link_id');
    }
}
