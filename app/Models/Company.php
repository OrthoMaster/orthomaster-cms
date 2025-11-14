<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active_flg' => 'boolean',
        'users_temp' => 'array'
    ];

    public function oldVersion()
    {
        return $this->hasOne(OldVersionCompany::class, 'id_office', 'id_office');
    }

    public function establishment()
    {
        return $this->belongsTo(Establishment::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('manager_flg')
            ->withTimestamps();
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_company');
    }

    public static function getAll()
    {
        return Company::where('active_flg', true)
            ->orderBy('name')
            ->get();
    }

    public static function findById($id)
    {
        return Company::where('id', $id)
            ->with(['establishment', 'users', 'users.organization', 'users.profession'])
            ->first();
    }

    public static function findByInviteCode($code)
    {
        return Company::where('invite_code', $code)
            ->with(['establishment', 'users', 'users.role', 'users.organization', 'users.profession'])
            ->first();
    }
}
