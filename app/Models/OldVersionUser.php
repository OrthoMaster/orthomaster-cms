<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OldVersionUser extends Model
{
    protected $table = 'user';
    protected $guarded = ['id'];
    public $timestamps = false;
}
