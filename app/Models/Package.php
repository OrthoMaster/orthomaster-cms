<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $guarded = ['id'];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('order_dsp');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'package_user');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'package_company');
    }

    public function scopeActive($query)
    {
        return $query->where('active_flg', true);
    }

    /** Not use */
    public static function getAll($onlyActive = false)
    {
        if ($onlyActive) {
            return Package::with([
                'modules' => function ($query) {
                    $query->active();
                },
                'modules.lessons' => function ($query) {
                    $query->active();
                },
                'training'
            ])
                ->active()
                ->orderBy('order_dsp')
                ->get();
        }

        return Package::with(['modules', 'modules.lessons', 'training'])
            ->orderBy('order_dsp')
            ->get();
    }
}
