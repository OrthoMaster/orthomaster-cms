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
            ->orderBy('name')
            ->withPivot('manager_flg')
            ->withTimestamps();
    }

    public function hasManager()
    {
        return $this->users()->wherePivot('manager_flg', true)->exists();
    }

    public function managers()
    {
        return $this->users()
            ->orderBy('name')    
            ->wherePivot('manager_flg', true)
            ->with('organization')
            ->get();
    }

    public function members()
    {
        return $this->users()
            ->orderBy('name')    
            ->wherePivot('manager_flg', false)
            ->with('organization')
            ->get();
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_company');
    }

    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function usersWithPackages()
    {
        return $this->users()->with(['packages', 'packages.training'])->get();
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

    public static function findByJoinCode($code)
    {
        return Company::where('join_code', $code)->first();
    }

    public function getMemberOrganizationIds()
    {
        return $this->users()
            ->whereNotNull('funding_organization_id')
            ->pluck('funding_organization_id')
            ->unique()
            ->sort()
            ->values();
    }

    public function getMemberByOrganization($organizationId)
    {
        return $this->users()
            ->whereNotNull('funding_organization_id')
            ->where('funding_organization_id', $organizationId)
            ->get();
    }
}
