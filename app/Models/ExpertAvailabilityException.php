<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Exception ponctuelle sur une date.
 * Priorité lors du calcul des créneaux : exception d'une demi-journée
 * > exception de la journée entière (period NULL) > règle récurrente.
 */
class ExpertAvailabilityException extends Model
{
    public const TYPE_LABELS = [
        'add'   => 'Ajout',
        'block' => 'Blocage',
    ];

    protected $fillable = [
        'expert_id',
        'date',
        'period',
        'type',
    ];

    protected $casts = [
        'expert_id' => 'integer',
        'date'      => 'date',
    ];

    public function getPeriodLabelAttribute(): string
    {
        return $this->period === null
            ? 'Journée entière'
            : (ExpertAvailabilityRule::PERIOD_LABELS[$this->period] ?? '');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? '';
    }
}