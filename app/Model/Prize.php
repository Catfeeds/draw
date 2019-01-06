<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prize extends Model
{
//    use SoftDeletes;

    protected $table = 'prize';
    protected $dateFormat = 'U';
//    protected $dates = ['deleted_at'];
    protected $primaryKey = 'prize_id';
}
