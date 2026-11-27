<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Suivi « retour au sport » d'un patient SCAT6 après une commotion (6 étapes). */
class ScatRts extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CLOSED = 'closed';

    /** Étape à laquelle l'entraînement avec contact est autorisé (certificat « contact »). */
    public const STEP_CONTACT = 5;
    /** Étape du retour à la compétition (certificat « compétition », fin du suivi). */
    public const STEP_COMPETITION = 6;

    protected $table = 'scat_rts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'steps'        => 'array',
            'setbacks'     => 'array',
            'injury_at'    => 'date',
            'completed_at' => 'datetime',
            'current_step' => 'integer',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(ScatPatient::class, 'scat_patient_id');
    }

    /** Début d'une étape (null si pas atteinte). */
    public function stepStartedAt(int $step): ?string
    {
        return $this->steps[(string) $step]['started_at'] ?? null;
    }
}
