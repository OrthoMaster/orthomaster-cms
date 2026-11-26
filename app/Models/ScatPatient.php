<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Patient SCAT6 d'un médecin (nom libre + année de naissance : données anonymisées). */
class ScatPatient extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
        ];
    }

    public function assessments()
    {
        return $this->hasMany(ScatAssessment::class);
    }
}
