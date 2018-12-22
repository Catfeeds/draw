<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BusinessHall extends Model
{
    protected $table = 'business_hall';
    protected $dates = ['deleted_at'];
    protected $dateFormat = 'U';
    protected $primaryKey = 'business_hall_id';
    protected $guarded = [];
}
