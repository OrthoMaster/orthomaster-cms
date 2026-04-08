<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function prices()
    {
        return $this->hasMany(PlanPrice::class);
    }
 
    public function features()
    {
        return $this->hasMany(PlanFeature::class)->orderBy('order_dsp');
    }
 
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
 
    public function monthlyPrice()
    {
        return $this->prices()->where('billing_cycle', 'monthly')->where('is_active', true)->first();
    }
 
    public function yearlyPrice()
    {
        return $this->prices()->where('billing_cycle', 'yearly')->where('is_active', true)->first();
    }
 
    public function yearlySavingPercent(): int
    {
        $monthly = $this->monthlyPrice()?->price;
        $yearly  = $this->yearlyPrice()?->price;
 
        if (! $monthly || ! $yearly || $monthly == 0) return 0;
 
        return (int) round((1 - ($yearly / $monthly)) * 100);
    }
}
