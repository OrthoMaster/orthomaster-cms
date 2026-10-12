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
        'min_notice_hours',
        'max_advance_months',
        'morning_start',
        'morning_end',
        'afternoon_start',
        'afternoon_end',
    ];

    protected $casts = [
        'expert_id'          => 'integer',
        'enabled_flg'        => 'boolean',
        'min_notice_hours'   => 'integer',
        'max_advance_months' => 'integer',
    ];
}