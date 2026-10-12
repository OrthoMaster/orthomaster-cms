<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Motif de refus proposé à l'expert.
 * Jamais supprimé, seulement désactivé : les réservations refusées
 * y feront référence (bookings.decline_reason_id, phase 3).
 */
class BookingDeclineReason extends Model
{
    protected $fillable = [
        'label',
        'order_dsp',
        'active_flg',
    ];

    protected $casts = [
        'order_dsp'  => 'integer',
        'active_flg' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active_flg', true)->orderBy('order_dsp')->orderBy('id');
    }
}