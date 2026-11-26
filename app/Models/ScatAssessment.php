<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Examen SCAT6 hors terrain : brouillon sauvegardé à chaque étape, puis signé (completed). */
class ScatAssessment extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data'         => 'array',
            'summary'      => 'array',
            'injury_at'    => 'datetime',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(ScatPatient::class, 'scat_patient_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
