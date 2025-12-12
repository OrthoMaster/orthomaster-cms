<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyJoinRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'invite_flg' => 'boolean'
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function company()
    {
        return $this->hasOne(Company::class);
    }

    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }
}
