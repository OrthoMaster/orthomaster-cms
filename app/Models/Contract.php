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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public static function getByUser($userId) {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        $company = $user->companiesUserIsManager()->first();
        if (!$company) {
            return null;
        }

        return Contract::where('company_id', $company->id)
            ->where('status', Status::ACTIVE->value)
            ->orderBy('updated_at')
            ->get();
    }
}
