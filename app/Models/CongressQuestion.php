<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CongressQuestion extends Model
{
    protected $guarded = [];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CongressParticipant::class, 'participant_id');
    }
}
