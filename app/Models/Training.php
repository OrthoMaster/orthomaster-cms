<?php

namespace App\Models;

use App\Status;
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
        return $query->where('status', Status::ACTIVE);
    }

    public static function getAll($onlyActive = false)
    {
        if ($onlyActive) {
            return Training::with([
                'packages' => function ($query) {
                    $query->active();
                },
                'packages.modules' => function ($query) {
                    $query->active()->orderBy('order_dsp');
                },
                'packages.modules.lessons' => function ($query) {
                    $query->published()->orderBy('order_dsp');
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
