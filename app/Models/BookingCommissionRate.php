<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCommissionRate extends Model
{
    protected $fillable = [
        'expert_id',
        'rate',
        'effective_from',
    ];

    protected $casts = [
        'expert_id'      => 'integer',
        // decimal(5,2) revient en "20.00" ; float pour afficher « 20 ».
        'rate'           => 'float',
        'effective_from' => 'date',
    ];

    /**
     * Taux en vigueur à une date donnée (Y-m-d, aujourd'hui par défaut).
     * Un taux propre à l'expert prime sur le taux général, quelle que soit sa date.
     * Renvoie 0 si aucun taux n'est défini.
     */
    public static function rateFor(?int $expertId, ?string $date = null): float
    {
        $date ??= now()->toDateString();

        $row = static::query()
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($expertId) {
                $q->whereNull('expert_id');
                if ($expertId) {
                    $q->orWhere('expert_id', $expertId);
                }
            })
            ->orderByRaw('expert_id IS NULL')
            ->orderByDesc('effective_from')
            ->first();

        return $row ? (float) $row->rate : 0.0;
    }
}