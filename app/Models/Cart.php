<?php

namespace App\Models;

use App\Status;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $guarded = ['id'];
    protected $appends = ['duration_text', 'member_ids', 'members_grouped'];

    protected $casts = [
        'price_total_ht' => 'decimal:2',
        'tva_total' => 'decimal:2',
        'price_total' => 'decimal:2',
    ];

    public function getDurationTextAttribute()
    {
        if (!$this->duration_total) {
            return '0m';
        }

        $hours = floor($this->duration_total / 60);
        $minutes = $this->duration_total % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    public function getMemberIdsAttribute()
    {
        return $this->members->pluck('id')->toArray();
    }

    public function getMembersGroupedAttribute()
    {
        return $this->members
            ->groupBy('funding_organization_id')
            ->map(function ($group, $orgId) {
                return [
                    'funding_organization_id' => $orgId,
                    'funding_organization_name' => $group->first()->organization?->name,
                    'members' => $group->map(fn($m) => [
                        'id' => $m->id,
                        'email' => $m->email,
                        'name' => $m->name,
                        'first_name' => $m->first_name
                    ])->values()
                ];
            })
            ->values();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'cart_user')
            ->withTimestamps();
    }

    public static function getByUser($userId)
    {
        return Cart::where('user_id', $userId)
            ->with(['items', 'items.package', 'members', 'members.organization'])
            ->orderBy('created_at')
            ->get();
    }

    public static function findByUser($userId, $cartId = null)
    {
        if ($cartId) {
            return Cart::where('user_id', $userId)
                ->where('id', $cartId)
                ->with('items')
                ->first();
        }

        $cart = Cart::where('user_id', $userId)
            ->with('items')
            ->first();
        if (!$cart) {
            return Cart::create(['user_id' => $userId]);
        }

        return $cart;
    }

    public function updateTotalDuration()
    {
        // Load items with packages
        $totalDuration = $this->items()
            ->with('package')
            ->get()
            ->sum(function ($items) {
                return $items->package->duration ?? 0;
            });

        $this->duration_total = $totalDuration;
        $this->save();

        return $totalDuration;
    }

    public function estimatePriceTotal() {
        $unitPrice = (float) setting('system.price_per_hour');
        $tva = (float) setting('system.tva');
        $totalHT = round(($this->duration_total / 60) * sizeof($this->members) * $unitPrice, 2);

        $this->unit_price = $unitPrice;
        $this->tva = $tva;
        $this->price_total_ht = $totalHT;
        $this->tva_total = round($totalHT * $tva / 100, 2);
        $this->price_total = $totalHT + round($totalHT * $tva / 100, 2);

        $this->status = Status::PENDING->value;

        $this->save();
    }
}
