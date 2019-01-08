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

    public function prizes()
    {
        return $this->hasMany('App\Model\BusinessHallPrize', 'business_hall_id', 'business_hall_id');
    }
}
