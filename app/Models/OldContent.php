<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OldContent extends Model
{
    protected $table = 'content';
    protected $primaryKey = 'id_content';

    protected $guarded = ['id'];
}
