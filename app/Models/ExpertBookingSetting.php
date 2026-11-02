<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages de prise de rendez-vous d'un expert.
 * Une colonne NULL signifie « valeur système » : utiliser
 * ExpertBookingSetupService::resolved() plutôt que lire les colonnes directement.
 */
class ExpertBookingSetting extends Model
{
    protected $fillable = [
        'expert_id',
        'enabled_flg',
        'timezone',
        'min_notice_days',
        'max_advance_months',
        'morning_start',
        'morning_end',
        'afternoon_start',
        'afternoon_end',
        'consultation_enabled',
        'consultation_price',
        'consultation_max_minutes',
    ];

    protected $casts = [
        'expert_id'          => 'integer',
        'enabled_flg'        => 'boolean',
        'min_notice_days'    => 'integer',
        'max_advance_months' => 'integer',
        'consultation_enabled'     => 'boolean',
        'consultation_price'       => 'decimal:2',
        'consultation_max_minutes' => 'integer',
    ];
}