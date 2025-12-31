<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'active_flg' => 'boolean',
        'first_purchase_only' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active_flg', true);
    }
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('active_flg', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            });
    }

    // Helper methods
    public function isValid(): bool
    {
        $now = now();

        // Check active
        if (!$this->active_flg) {
            return false;
        }

        // Check date range
        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }
        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        // Check limit usage
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function canBeUsedBy(User $user, float $orderAmount, array $courseIds = []): array
    {
        // Check if promotion is valid
        if (!$this->isValid()) {
            return ['valid' => false, 'message' => 'Le code promotionnel est invalide ou a expiré.'];
        }

        // Check minimum purchase amount
        if ($orderAmount < $this->min_purchase_amount) {
            return [
                'valid' => false,
                'message' => "La valeur minimale de commande est de " . number_format($this->min_purchase_amount) . " €"
            ];
        }

        // TODO: Check per-user usage limit
        // $userUsageCount = $this->usages()->where('user_id', $user->id)->count();
        // if ($userUsageCount >= $this->usage_limit_per_user) {
        //     return ['valid' => false, 'message' => "Ce code n'est plus disponible."];
        // }

        // TODO: Check first purchase only -> $user->orders()
        if ($this->first_purchase_only) {
            $hasPreviousPurchase = $user->orders()->where('status', 'completed')->exists();
            if ($hasPreviousPurchase) {
                return ['valid' => false, 'message' => "Ce code s'applique uniquement à la première commande."];
            }
        }

        // TODO: Check applicable courses
        // if ($this->applicable_to || $this->applicable_to !== 'all') { }

        return ['valid' => true, 'message' => 'Code promotionnel valide'];
    }

    public function calculateDiscount(float $orderAmount): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = $orderAmount * ($this->discount_value / 100);
            
            // Apply max discount cap if set
            if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
                $discount = $this->max_discount_amount;
            }
            
            return round($discount, 2);
        }

        // Fixed amount discount
        return min($this->discount_value, $orderAmount);
    }
}
