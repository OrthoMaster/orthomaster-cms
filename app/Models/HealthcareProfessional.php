<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthcareProfessional extends Model
{
    protected $guarded = ['id'];

    public function diplomes()
    {
        return $this->hasMany(HealthcareProfessionalDiplome::class);
    }

    public function activities()
    {
        return $this->hasMany(HealthcareProfessionalActivity::class);
    }
}
