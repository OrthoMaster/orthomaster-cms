<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Formation extends Model
{
    protected $table = 'formation';
    protected $primaryKey = 'id_formation';
    public $timestamps = false;

    protected $guarded = ['id_formation'];

    public function blocks()
    {
        return $this->hasMany(Block::class, 'id_formation', 'id_formation');
    }
}
