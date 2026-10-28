<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Date précise proposée par une offre (calendrier « Dates précises »).
 * Les dates passées sont gardées : elles n'ont plus d'effet mais restent lisibles.
 */
class ExpertOfferDate extends Model
{
    protected $fillable = [
        'offer_id',
        'mode',
        'date',
        'period',
    ];

    protected $casts = [
        'offer_id' => 'integer',
        'date'     => 'date:Y-m-d',
    ];

    /** Clé « Y-m-d|période », la même que celle des formulaires. */
    public function getKeyStringAttribute(): string
    {
        return $this->date->toDateString() . '|' . $this->period;
    }
}