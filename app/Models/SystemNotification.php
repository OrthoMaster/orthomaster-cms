<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'read_flg' => 'boolean',
        'meta_data' => 'json'
    ];

    public function receiver()
    {
        return $this->hasOne(User::class);
    }

    public function actor()
    {
        return $this->hasOne(User::class, 'id', 'actor_id');
    }

    public static function getByReceiver($id)
    {
        return SystemNotification::where('user_id', $id)
            ->where('read_flg', false)
            ->get();
    }
}
