<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    protected $table = 'award';
    protected $dateFormat = 'U';
    protected $primaryKey = 'award_id';
}
