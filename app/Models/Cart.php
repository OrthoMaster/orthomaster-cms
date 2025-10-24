<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price_total' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public static function getByUser($userId) {
        return Cart::where('user_id', $userId)
            ->with('items')
            ->orderBy('created_at')
            ->get();
    }

    public static function findByUser($userId, $cartId = null) {
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
}
