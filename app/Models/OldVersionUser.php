<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OldVersionUser extends Model
{
    protected $table = 'user';
    protected $primaryKey = 'id_user';
    protected $guarded = ['id_user'];
    public $timestamps = false;
}
