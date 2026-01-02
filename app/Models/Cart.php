<?php

namespace App\Models;

use App\Organization;
use App\Status;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $guarded = ['id'];
    protected $appends = ['duration_text', 'member_ids', 'members_grouped', 'contract_creatable'];

    protected $casts = [
        'price_total_ht' => 'decimal:2',
        'tva_total' => 'decimal:2',
        'price_total' => 'decimal:2',
        'contract_creatable' => 'boolean',
        'company_flg' => 'boolean'
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', '!=', Status::DELETED->value);
    }
    public function scopeDeleted($query)
    {
        return $query->where('status', Status::DELETED->value);
    }
    public function scopeIndividual($query)
    {
        return $query->where('company_flg', false);
    }
    public function scopeCompany($query)
    {
        return $query->where('company_flg', true);
    }

    // Append Cols
    public function getDurationTextAttribute()
    {
        if (!$this->duration_total) {
            return '0 m';
        }

        $hours = floor($this->duration_total / 60);
        $minutes = $this->duration_total % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours} h {$minutes} m";
        } elseif ($hours > 0) {
            return "{$hours} h";
        } else {
            return "{$minutes} m";
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

    public function getContractCreatableAttribute()
    {
        $checkHasFifpl = $this->members()
            ->whereHas('organization', function ($query) {
                $query->where('code', 'fifpl');
            })
            ->exists();

        if ($checkHasFifpl) {
            return $this->duration_total >= Organization::FIFPL_MIN_DURATION->value;
        }

        $checkHasOpco = $this->members()
            ->whereHas('organization', function ($query) {
                $query->where('code', 'opco-ep');
            })
            ->exists();

        if ($checkHasOpco) {
            return $this->duration_total >= Organization::OPCO_MIN_DURATION->value;
        }

        return false;
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

    public static function getByUser($userId, $companyCartFlg = false)
    {
        if ($companyCartFlg) {
            return Cart::where('user_id', $userId)
                ->company()
                ->active()
                ->with(['items', 'items.package', 'members', 'members.organization'])
                ->orderBy('created_at')
                ->get();
        }

        return Cart::where('user_id', $userId)
            ->individual()
            ->active()
            ->with(['items', 'items.package', 'members', 'members.organization'])
            ->orderBy('created_at')
            ->get();
    }

    public static function findByUser($userId, $cartId = null, $companyCartFlg = false)
    {
        if ($cartId) {
            return Cart::where('user_id', $userId)
                ->where('id', $cartId)
                ->active()
                ->with('items')
                ->first();
        }

        if ($companyCartFlg) {
            $cart = Cart::where('user_id', $userId)
                ->company()
                ->active()
                ->with('items')
                ->first();
        } else {
            $cart = Cart::where('user_id', $userId)
                ->individual()
                ->active()
                ->with('items')
                ->first();
        }

        if (!$cart) {
            return Cart::create([
                'user_id' => $userId,
                'company_flg' => $companyCartFlg
            ]);
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

    public function estimatePriceTotal()
    {
        $unitPrice = (float) setting('system.unit_price');
        $tva = (float) setting('system.tva');
        $cntMembers = sizeof($this->members) > 0 ? sizeof($this->members) : 1;

        $totalHT = round(($this->duration_total / 60) * $cntMembers * $unitPrice, 2);

        $this->unit_price = $unitPrice;
        $this->tva = $tva;
        $this->price_total_ht = $totalHT;
        $this->tva_total = round($totalHT * $tva / 100, 2);
        $this->price_total = $totalHT + round($totalHT * $tva / 100, 2);

        $this->status = Status::PENDING->value; // is calculated
        $this->save();
    }

    public function hasOpco()
    {
        return $this->members()
            ->whereHas('organization', function ($query) {
                $query->where('code', 'opco-ep');
            })
            ->exists();
    }

    public function hasFifpl()
    {
        return $this->members()
            ->whereHas('organization', function ($query) {
                $query->where('code', 'fifpl');
            })
            ->exists();
    }

    public function groupItemsByDurationBestFit($groupDuration = 43200)
    {
        // Eager load package
        $items = $this->items()
            ->with('package')
            ->get()
            ->sortByDesc(function ($item) {
                return $item->package->duration ?? 0;
            })
            ->values();

        $groups = collect([]);

        foreach ($items as $item) {
            $itemDuration = $item->package->duration ?? 0;

            $bestGroup = null;
            $minRemaining = $groupDuration + 1;

            // Find group fit with item duration
            foreach ($groups as $group) {
                $currentDuration = $this->calculateGroupDuration($group);
                $remaining = $groupDuration - $currentDuration;

                if ($remaining >= $itemDuration && $remaining < $minRemaining) {
                    $bestGroup = $group;
                    $minRemaining = $remaining;
                }
            }

            if ($bestGroup) {
                $bestGroup->push($item);
            } else {
                $groups->push(collect([$item]));
            }
        }

        return $groups;
    }

    private function calculateGroupDuration($group)
    {
        return $group->sum(function ($item) {
            return $item->package->duration ?? 0;
        });
    }
}
