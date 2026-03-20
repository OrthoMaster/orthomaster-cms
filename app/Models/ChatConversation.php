<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
 
    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }
 
    public function latestMessage()
    {
        return $this->hasMany(ChatMessage::class)->latest('sent_at')->limit(1);
    }
}
