<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExpertOfferImage extends Model
{
    public const DISK   = 's3';
    public const FOLDER = 'expert_topic_image';

    protected $fillable = [
        'offer_id',
        'path',
        'caption',
        'width',
        'height',
        'order_dsp',
    ];

    protected $casts = [
        'offer_id'  => 'integer',
        'width'     => 'integer',
        'height'    => 'integer',
        'order_dsp' => 'integer',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ExpertOffer::class, 'offer_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }

    /** Forme commune pour l'API (fiche du thème, espace expert). */
    public function payload(): array
    {
        return [
            'id'      => $this->id,
            'url'     => $this->url,
            'caption' => $this->caption,
            'width'   => $this->width,
            'height'  => $this->height,
        ];
    }
}