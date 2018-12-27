<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Sign extends Model
{
    protected $table = 'sign';
    protected $dateFormat = 'U';
    protected $primaryKey = 'sign_id';
    public $timestamps = false;
}
