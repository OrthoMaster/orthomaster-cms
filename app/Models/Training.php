<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $guarded = ['id'];

    public function packages()
    {
        return $this->hasMany(Package::class)->orderBy('order_dsp');
    }

    public function scopeActive($query)
    {
        return $query->where('active_flg', true);
    }

    public static function getAll($onlyActive = false)
    {
        if ($onlyActive) {
            return Training::with([
                'packages' => function ($query) {
                    $query->active();
                },
                'packages.modules' => function ($query) {
                    $query->active();
                },
                'packages.modules.lessons' => function ($query) {
                    $query->active();
                },
            ])
                ->active()
                ->orderBy('order_dsp')
                ->get();
        }

        return Training::with(['packages', 'packages.modules', 'packages.modules.lessons'])
            ->orderBy('order_dsp')
            ->get();
    }
}
