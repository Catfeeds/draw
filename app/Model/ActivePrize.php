<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ActivePrize extends Model
{
    protected $table = 'active_prize';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
