<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MktShortLink extends Model
{
    protected $table = 'mkt_short_links';

    protected $fillable = [
        'code', 'destination_url', 'original_url', 'title', 'source_type',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'tracking_enabled', 'clicks_count', 'last_clicked_at',
    ];

    protected $casts = [
        'tracking_enabled' => 'boolean',
        'last_clicked_at'  => 'datetime',
    ];

    public function clickEvents(): HasMany
    {
        return $this->hasMany(MktClickEvent::class, 'short_link_id');
    }

    /** Génère un code unique de 6 caractères (base62). */
    public static function generateUniqueCode(int $length = 6): string
    {
        do {
            $code = Str::lower(Str::random($length));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function getShortUrlAttribute(): string
    {
        return config('app.short_link_domain', 'https://go.orthomaster.fr') . '/sh/' . $this->code;
    }
}
