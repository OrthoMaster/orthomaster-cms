<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Politique d'annulation et d'absence, historisée par date d'effet et définie
 * séparément pour les rendez-vous en ligne et en présentiel.
 */
class BookingPolicy extends Model
{
    protected $fillable = [
        'effective_from',
        'mode',
        'free_cancel_hours',
        'late_cancel_penalty_pct',
        'user_noshow_refund_pct',
    ];

    protected $casts = [
        'effective_from'          => 'date',
        'free_cancel_hours'       => 'integer',
        'late_cancel_penalty_pct' => 'float',
        'user_noshow_refund_pct'  => 'float',
    ];

    /** Politique en vigueur à une date donnée (Y-m-d, aujourd'hui par défaut) pour un mode. */
    public static function currentFor(?string $date = null, string $mode = ExpertOfferMode::ONLINE): ?self
    {
        $date ??= now()->toDateString();

        return static::query()
            ->where('mode', $mode)
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }
}