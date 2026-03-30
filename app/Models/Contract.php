<?php

namespace App\Models;

use App\Status;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y H:i',
        'updated_at' => 'datetime:d-m-Y H:i',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'created_by');
    }

    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'id', 'beneficiary_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function getByUser($userId)
    {
        return Contract::where('created_by', $userId)
            ->orWhere('beneficiary_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
