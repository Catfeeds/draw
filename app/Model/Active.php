<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Active extends Model
{
    protected $table = 'active';
    protected $dateFormat = 'U';
    protected $dates = ['deleted_at'];
    protected $primaryKey = 'active_id';
}
