<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPolicy extends Model
{
    protected $fillable = [
        'effective_from',
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

    /** Politique en vigueur à une date donnée (Y-m-d, aujourd'hui par défaut). */
    public static function currentFor(?string $date = null): ?self
    {
        $date ??= now()->toDateString();

        return static::query()
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }
}