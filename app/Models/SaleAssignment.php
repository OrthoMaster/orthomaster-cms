<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleAssignment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:d-m-Y H:i',
        'updated_at' => 'datetime:d-m-Y H:i',
    ];

    public function sale()
    {
        return $this->belongsTo(User::class, 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
