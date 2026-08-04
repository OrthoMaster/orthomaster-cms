<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Event extends Model
{
    use Searchable;

    protected $guarded = ['id'];

    public $timestamps = false;

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
