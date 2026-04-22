<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreRegDiplomaRequest extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'id', 'created_by');
    }

    public static function findByUser($userId) {
        return PreRegDiplomaRequest::where('user_id', $userId)->first();
    }
}
