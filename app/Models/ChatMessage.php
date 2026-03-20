<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
 
    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class);
    }
}
