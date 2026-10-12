<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpertAvailabilityRule extends Model
{
    /** Convention ISO-8601, identique à Carbon::dayOfWeekIso. */
    public const WEEKDAY_LABELS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    public const PERIOD_LABELS = [
        'morning'   => 'Matin',
        'afternoon' => 'Après-midi',
    ];

    protected $fillable = [
        'expert_id',
        'weekday',
        'period',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'expert_id'   => 'integer',
        'weekday'     => 'integer',
        'valid_from'  => 'date',
        'valid_until' => 'date',
    ];

    public function getWeekdayLabelAttribute(): string
    {
        return self::WEEKDAY_LABELS[$this->weekday] ?? '';
    }

    public function getPeriodLabelAttribute(): string
    {
        return self::PERIOD_LABELS[$this->period] ?? '';
    }
}