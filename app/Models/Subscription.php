<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'trial_ends_at'  => 'datetime',
        'canceled_at'    => 'datetime',
        'auto_renew'     => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    public function planPrice()
    {
        return $this->belongsTo(PlanPrice::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function usageRecords()
    {
        return $this->hasMany(TrkPlanUsage::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at->isFuture();
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->ends_at, false));
    }

    public static function increaseUsage(int $userId, int $subscriptionId, string $metric, int $amount = 1)
    {
        return static::updateOrCreate(
            [
                'user_id'         => $userId,
                'subscription_id' => $subscriptionId,
                'metric'          => $metric,
                'period_date'     => now()->toDateString(),
            ],
            ['limit_value' => 0]
        )->tap(fn ($r) => $r->increment('used', $amount));
    }
}
