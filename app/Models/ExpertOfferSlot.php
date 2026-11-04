<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Demi-journée de la grille hebdomadaire retenue par une offre
 * (calendrier « Certaines demi-journées »).
 */
class ExpertOfferSlot extends Model
{
    protected $fillable = [
        'offer_id',
        'mode',
        'weekday',
        'period',
    ];

    protected $casts = [
        'offer_id' => 'integer',
        'weekday'  => 'integer',
    ];

    /** Clé « jour|période », la même que celle des formulaires. */
    public function getKeyStringAttribute(): string
    {
        return $this->weekday . '|' . $this->period;
    }
}