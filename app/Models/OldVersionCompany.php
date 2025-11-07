<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OldVersionCompany extends Model
{
    protected $table = 'office';
    protected $primaryKey = 'id_office';
    protected $guarded = ['id_office'];
    public $timestamps = false;
}
