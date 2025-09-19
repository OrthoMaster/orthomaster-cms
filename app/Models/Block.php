<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public static function getFirstByFormation($formationId) {
        $blocks = Block::where('training_id', $formationId)->get();
        if (sizeof($blocks) > 0) {
            return $blocks->first();
        }

        return Block::create([
            'training_id' => $formationId
        ]);
    }
}
